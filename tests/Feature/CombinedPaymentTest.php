<?php

use App\Actions\PaymentService;
use App\Actions\PlatformSettings;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\BillAdjustment;
use App\Models\ClientNotice;
use App\Models\Connection;
use App\Models\Consumer;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

function combinedBills(array $amounts = ['100.01', '50.02', '25.03']): array
{
    $consumer = Consumer::factory()->create();
    $client = User::factory()->create(['consumer_id' => $consumer->id]);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $consumer->water_provider_id]);
    $bills = collect($amounts)->map(function ($amount, $index) use ($consumer) {
        $connection = Connection::factory()->create(['consumer_id' => $consumer->id, 'water_provider_id' => $consumer->water_provider_id]);

        return Bill::factory()->create(['connection_id' => $connection->id, 'period_month' => today()->startOfMonth()->subMonths($index + 1)->toDateString(), 'total' => $amount]);
    })->sortBy('period_month')->values();

    return [$consumer, $client, $staff, $bills];
}

function submitCombined($test, User $client, string $amount, array $extra = [])
{
    Storage::fake('local');

    return $test->actingAs($client)->post(route('portal.payments.store'), $extra + [
        '_form' => 'submit-payment', 'payment_scope' => 'all', 'reference' => 'COMBINED-REF', 'amount' => $amount,
        'paid_at' => today()->toDateString(), 'proof' => UploadedFile::fake()->create('receipt.pdf', 5, 'application/pdf'),
    ]);
}

test('all payment plans fund oldest bills using exact cents without changing pending balances', function (string $amount, array $expected) {
    [$consumer, $client, $staff, $bills] = combinedBills();

    submitCombined($this, $client, $amount)->assertSessionHasNoErrors();

    $payment = Payment::sole();
    expect($payment->bill_id)->toBe($bills->first()->id);
    expect($payment->items()->orderBy('id')->pluck('amount')->all())->toBe($expected);
    expect($payment->items()->orderBy('id')->pluck('bill_id')->all())->toBe($bills->take(count($expected))->pluck('id')->all());
    expect($payment->allocations)->toHaveCount(0);
    expect($bills->map(fn ($bill) => $bill->fresh()->balance())->all())->toBe(['25.03', '50.02', '100.01']);

    app(PaymentService::class)->verify($payment, $staff);
    expect($payment->fresh()->allocations()->orderBy('id')->pluck('amount')->all())->toBe($expected);
    expect($payment->fresh()->receipt_snapshot['version'])->toBe(1);
    expect(AuditLog::where('action', 'payment.verified')->count())->toBe(1);
    expect(ClientNotice::where('user_id', $client->id)->where('title', 'Payment confirmed')->count())->toBe(1);
})->with([
    'all balances' => ['175.06', ['25.03', '50.02', '100.01']],
    'partial last funded bill' => ['75.06', ['25.03', '50.02', '0.01']],
    'exact boundary omits zero items' => ['25.03', ['25.03']],
]);

test('all payments include more than fifty bills across meters and future due dates', function () {
    [$consumer, $client, $staff, $bills] = combinedBills(array_fill(0, 52, '1.01'));
    $this->actingAs($client)->get(route('portal.section', 'payments'))->assertOk()
        ->assertSee('Pay all unpaid bills')->assertViewHas('allBills', fn ($allBills) => $allBills->count() === 52);

    submitCombined($this, $client, '52.52')->assertSessionHasNoErrors();
    expect(Payment::sole()->items)->toHaveCount(52);
});

test('single payment defaults remain compatible and reject foreign or void targets and overpayments', function () {
    [$consumer, $client, $staff, $bills] = combinedBills();
    $foreign = Bill::factory()->create();
    submitCombined($this, $client, '1.00', ['payment_scope' => 'single', 'bill_id' => $foreign->id])->assertSessionHasErrors('bill_id');
    submitCombined($this, $client, '999.00')->assertSessionHasErrors('amount');
    $bills->first()->update(['status' => 'void']);
    submitCombined($this, $client, '1.00', ['payment_scope' => 'single', 'bill_id' => $bills->first()->id])->assertSessionHasErrors('amount');
    submitCombined($this, $client, '1.00', ['payment_scope' => 'bogus'])->assertSessionHasErrors('payment_scope');
    $this->assertDatabaseCount('payments', 0);

    submitCombined($this, $client, '2.00', ['payment_scope' => null, 'bill_id' => $bills->last()->id])->assertSessionHasNoErrors();
    expect(Payment::sole()->items->sole()->bill_id)->toBe($bills->last()->id);
});

test('submitted target arrays cannot redirect an all payment or expose other accounts', function () {
    [$consumer, $client, $staff, $bills] = combinedBills();
    $foreign = Bill::factory()->create();
    submitCombined($this, $client, '175.06', ['bill_id' => $foreign->id, 'bill_ids' => [$foreign->id], 'items' => [['bill_id' => $foreign->id, 'amount' => '175.06']]])->assertSessionHasNoErrors();
    expect(Payment::sole()->items->pluck('bill_id')->all())->toBe($bills->pluck('id')->all());
    $this->actingAs($client)->get(route('portal.section', 'payments'))->assertOk()->assertDontSee($foreign->number);
});

test('a stale target fails atomically and never reallocates to a new bill', function (string $change) {
    [$consumer, $client, $staff, $bills] = combinedBills();
    submitCombined($this, $client, '175.06')->assertSessionHasNoErrors();
    $payment = Payment::sole();
    $target = $bills->last();
    if ($change === 'paid') {
        $other = Payment::factory()->create(['bill_id' => $target->id, 'amount' => '1.00']);
        app(PaymentService::class)->verify($other, $staff);
    } elseif ($change === 'void') {
        $target->update(['status' => 'void']);
    } elseif ($change === 'foreign') {
        $foreign = Bill::factory()->create();
        $payment->items()->where('bill_id', $target->id)->update(['bill_id' => $foreign->id]);
    } else {
        $payment->items()->where('bill_id', $target->id)->update(['amount' => '99.00']);
    }
    expect(fn () => app(PaymentService::class)->verify($payment, $staff))->toThrow(ValidationException::class);
    expect($payment->fresh()->status)->toBe('pending');
    expect($payment->fresh()->receipt_snapshot)->toBeNull();
    expect($payment->allocations()->count())->toBe(0);
    expect($bills->first()->fresh()->status)->toBe('unpaid');
})->with(['paid', 'void', 'foreign', 'wrong sum']);

test('verification preserves increased fees and rejects repeated verification', function () {
    [$consumer, $client, $staff, $bills] = combinedBills();
    submitCombined($this, $client, '175.06')->assertSessionHasNoErrors();
    $bills->last()->update(['total' => '110.01', 'late_fee' => '10.00']);
    $payment = Payment::sole();
    app(PaymentService::class)->verify($payment, $staff);
    expect($bills->last()->fresh()->balance())->toBe('10.00');
    expect($bills->last()->fresh()->status)->toBe('partially_paid');
    expect(fn () => app(PaymentService::class)->verify($payment, $staff))->toThrow(ValidationException::class);
    expect($payment->allocations()->count())->toBe(3);
});

test('rejecting a combined payment leaves every bill balance untouched', function () {
    [$consumer, $client, $staff, $bills] = combinedBills();
    submitCombined($this, $client, '175.06')->assertSessionHasNoErrors();
    $payment = Payment::sole();
    $this->actingAs($staff)->post(route('provider.payments.reject', $payment), ['remarks' => 'Unreadable proof'])->assertSessionHasNoErrors();
    expect($payment->fresh()->status)->toBe('rejected');
    expect($payment->allocations()->count())->toBe(0);
    expect($bills->map(fn ($bill) => $bill->fresh()->balance())->all())->toBe(['25.03', '50.02', '100.01']);
});

test('combined history and bill filters use funded associations for every payment state', function (string $status) {
    [$consumer, $client, $staff, $bills] = combinedBills();
    submitCombined($this, $client, '175.06')->assertSessionHasNoErrors();
    $payment = Payment::sole();
    if ($status === 'verified') {
        app(PaymentService::class)->verify($payment, $staff);
    } elseif ($status === 'rejected') {
        $payment->update(['status' => 'rejected']);
    }
    // An anchor alone must not override authoritative items / allocations.
    $unfunded = Bill::factory()->create(['connection_id' => Connection::factory()->create(['consumer_id' => $consumer->id, 'water_provider_id' => $consumer->water_provider_id])->id]);
    $payment->update(['bill_id' => $unfunded->id]);
    $this->actingAs($client)->get(route('portal.section', 'payments'))->assertOk()->assertSee($bills->first()->number)->assertSee($bills->last()->number);
    $this->actingAs($staff)->get(route('provider.payments.index', ['bill' => $bills->last()->id]))->assertOk()
        ->assertViewHas('payments', fn ($payments) => $payments->total() === 1 && $payments->first()->amountForBill($bills->last()) === '100.01')->assertSee('COMBINED-REF')->assertSee($bills->first()->number);
    $this->get(route('provider.payments.index', ['bill' => $unfunded->id]))->assertOk()->assertViewHas('payments', fn ($payments) => $payments->total() === 0);
    $this->get(route('provider.consumers.index', ['preview_consumer' => $consumer->id, 'period' => $bills->last()->period_month->format('Y-m')]))->assertOk()
        ->assertViewHas('monthlyReports', fn ($reports) => $reports->count() === 1 && $reports->first()['has_payment_activity']);
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin)->get(route('admin.payments.show', $payment))->assertOk()->assertSee($bills->first()->number)->assertSee($bills->last()->number)->assertDontSee($unfunded->number);
    $this->get(route('admin.payments.index'))->assertOk()->assertSee($bills->first()->number)->assertSee($bills->last()->number);
})->with(['pending', 'verified', 'rejected']);

test('verified receipt freezes identity charges and balances before later payments and adjustments', function () {
    [$consumer, $client, $staff, $bills] = combinedBills();
    $consumer->update(['name' => 'Original consumer']);
    $consumer->waterProvider->update(['name' => 'Original issuer']);
    BillAdjustment::factory()->create(['bill_id' => $bills->last()->id, 'type' => 'credit', 'amount' => '3.00', 'reason' => 'Original credit']);
    submitCombined($this, $client, '75.06')->assertSessionHasNoErrors();
    $payment = Payment::sole();
    app(PaymentService::class)->verify($payment, $staff);
    $url = route('payments.print', ['payment' => $payment, 'preview' => 1]);
    $documentContent = function (string $html): string {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return $document->saveHTML($document->getElementsByTagName('main')->item(0));
    };
    $before = $documentContent($this->actingAs($client)->get($url)->assertOk()->assertSee('Original consumer')->assertSee('Original issuer')->assertSee('Original credit')->assertSee('-3.00')->getContent());
    $snapshot = $payment->fresh()->receipt_snapshot;
    expect($snapshot['bills'][2]['balance_after'])->toBe('100.00');
    expect($snapshot['bills'][2]['applied'])->toBe('0.01');
    $consumer->update(['name' => 'Later consumer']);
    $consumer->waterProvider->update(['name' => 'Later issuer']);
    $later = Payment::factory()->create(['bill_id' => $bills->last()->id, 'amount' => '25.00']);
    app(PaymentService::class)->verify($later, $staff);
    $bills->last()->update(['total' => '125.01']);
    BillAdjustment::factory()->create(['bill_id' => $bills->last()->id, 'type' => 'debit', 'amount' => '25.00', 'reason' => 'Later charge']);
    $payment->update(['reference' => 'LATER-REFERENCE', 'method' => 'bank_transfer', 'paid_at' => today()->subYear()]);
    app(PlatformSettings::class)->put('date_format', 'Y-m-d');
    $this->travel(2)->days();
    expect($documentContent($this->get($url)->assertOk()->getContent()))->toBe($before);
    expect($payment->fresh()->receipt_snapshot)->toBe($snapshot);
});

test('legacy verified receipt identifies its live values and legacy pending rows still verify', function () {
    $payment = Payment::factory()->create(['status' => 'verified']);
    $client = User::factory()->create(['consumer_id' => $payment->consumer_id]);
    $this->actingAs($client)->get(route('payments.print', $payment))->assertOk()->assertSee('not a historical snapshot');
    $pending = Payment::factory()->create(['bill_id' => $payment->bill_id, 'amount' => '1.00']);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $pending->water_provider_id]);
    app(PaymentService::class)->verify($pending, $staff);
    expect($pending->fresh()->receipt_snapshot)->not->toBeNull();
});

test('payment modal prefills remaining balance and retains an entered partial amount on retry', function () {
    [$consumer, $client, $staff, $bills] = combinedBills();
    $bill = $bills->last();
    $payment = Payment::factory()->create(['bill_id' => $bill->id, 'amount' => '20.01']);
    app(PaymentService::class)->verify($payment, $staff);
    $page = route('portal.section', ['section' => 'payments', 'bill' => $bill->id]);
    $this->actingAs($client)->get($page)->assertOk()->assertSee('value="80.00"', false)->assertSee('x-model="amount"', false)->assertSee('payment_scope', false)->assertSee('Pay all unpaid bills');
    $this->from($page)->post(route('portal.payments.store'), ['_form' => 'submit-payment', 'bill_id' => $bill->id, 'reference' => 'RETRY', 'amount' => '2.34', 'paid_at' => today()->toDateString()])->assertSessionHasErrors('proof');
    session()->reflash();
    session()->save();
    $this->get($page)->assertOk()->assertSee('value="2.34"', false);
});

test('payment items follow payment deletion and do not break consumer deletion ordering', function () {
    [$consumer, $client, $staff, $bills] = combinedBills();
    submitCombined($this, $client, '175.06')->assertSessionHasNoErrors();
    $payment = Payment::sole();
    $payment->delete();
    $this->assertDatabaseCount('payment_bill_items', 0);
    submitCombined($this, $client, '175.06')->assertSessionHasNoErrors();
    $this->actingAs($staff)->delete(route('provider.consumers.destroy', $consumer), ['account_number' => $consumer->account_number])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('payment_bill_items', 0);
    $this->assertDatabaseMissing('consumers', ['id' => $consumer->id]);
});

test('same month plans use stable bill id order and exclude paid void and foreign provider bills', function () {
    [$consumer, $client, $staff, $bills] = combinedBills(['1.01', '2.02', '3.03']);
    foreach ($bills as $bill) {
        $bill->update(['period_month' => today()->startOfMonth()]);
    }
    $bills = $bills->sortBy('id')->values();
    $bills->last()->update(['status' => 'void']);
    $paid = Bill::factory()->create();
    $paid->update(['consumer_id' => $consumer->id]);
    $settled = Payment::factory()->create(['bill_id' => $bills->get(1)->id, 'amount' => '2.02']);
    app(PaymentService::class)->verify($settled, $staff);
    $this->actingAs($client)->get(route('portal.section', 'payments'))->assertOk()->assertDontSee('Pay all unpaid bills');
    submitCombined($this, $client, '1.01')->assertSessionHasNoErrors();
    $combined = Payment::where('reference', 'COMBINED-REF')->sole();
    expect($combined->items->pluck('bill_id')->all())->toBe([$bills->first()->id]);

    $bills->get(1)->update(['total' => '4.04']);
    submitCombined($this, $client, '2.00', ['reference' => 'SAME-MONTH'])->assertSessionHasNoErrors();
    $tied = Payment::where('reference', 'SAME-MONTH')->sole();
    expect($tied->items->pluck('bill_id')->all())->toBe($bills->take(2)->pluck('id')->all());
    expect($tied->items->pluck('amount')->all())->toBe(['1.01', '0.99']);
});

test('monthly reports count one combined payment once per month and use allocations for paid totals', function () {
    [$consumer, $client, $staff, $bills] = combinedBills();
    $bills->first()->update(['period_month' => $bills->last()->period_month]);
    submitCombined($this, $client, '125.05')->assertSessionHasNoErrors();
    $payment = Payment::sole();
    $period = $bills->last()->period_month->format('Y-m');
    $url = route('provider.consumers.index', ['preview_consumer' => $consumer->id, 'period' => $period]);
    $this->actingAs($staff)->get($url)->assertOk()->assertViewHas('monthlyReports', fn ($reports) => $reports->sole()['pending_count'] === 1);
    app(PaymentService::class)->verify($payment, $staff);
    $this->get($url)->assertOk()->assertViewHas('monthlyReports', fn ($reports) => $reports->sole()['paid'] === '75.03' && $reports->sole()['balance'] === '50.01');
    $this->get(route('provider.payments.index', ['bill' => $bills->last()->id]))->assertOk()
        ->assertViewHas('payments', fn ($payments) => $payments->sole()->amountForBill($bills->last()) === '75.03');
});

test('malicious persisted foreign associations cannot appear in histories or bill queries', function () {
    [$consumer, $client, $staff, $bills] = combinedBills();
    submitCombined($this, $client, '175.06')->assertSessionHasNoErrors();
    $payment = Payment::sole();
    $foreign = Bill::factory()->create(['water_provider_id' => $consumer->water_provider_id]);
    $payment->items()->where('bill_id', $bills->first()->id)->update(['bill_id' => $foreign->id]);
    $this->actingAs($client)->get(route('portal.section', 'payments'))->assertOk()->assertDontSee($foreign->number);
    $this->actingAs($staff)->get(route('provider.payments.index', ['bill' => $foreign->id]))->assertOk()
        ->assertViewHas('payments', fn ($payments) => $payments->total() === 0);
    expect(fn () => app(PaymentService::class)->verify($payment, $staff))->toThrow(ValidationException::class);
    expect($payment->allocations()->count())->toBe(0);
});

test('payment schema upgrade is reversible without fabricating legacy receipt snapshots', function () {
    $payment = Payment::factory()->create(['status' => 'verified']);
    $migration = require database_path('migrations/2026_11_15_000000_add_payment_bill_items_and_receipt_snapshot.php');
    $migration->down();
    expect(Schema::hasTable('payment_bill_items'))->toBeFalse();
    expect(Schema::hasColumn('payments', 'receipt_snapshot'))->toBeFalse();
    $migration->up();
    expect($payment->fresh()->receipt_snapshot)->toBeNull();
    expect($payment->fresh()->status)->toBe('verified');
});

test('all scope validation retries remain usable when only one unpaid bill remains', function () {
    [$consumer, $client, $staff, $bills] = combinedBills(['100.01', '50.02']);
    $settled = Payment::factory()->create(['bill_id' => $bills->first()->id, 'amount' => '50.02']);
    app(PaymentService::class)->verify($settled, $staff);
    $page = route('portal.section', 'payments');
    $this->actingAs($client)->from($page);
    submitCombined($this, $client, '150.03')->assertSessionHasErrors('amount');
    session()->reflash();
    session()->save();
    $response = $this->get($page)->assertOk()->assertSee('Pay all unpaid bills');
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $elements = new DOMXPath($document);
    expect($elements->query('//select[@name="payment_scope"]/option[@value="all"][@selected]'))->toHaveCount(1);
    submitCombined($this, $client, '100.01')->assertSessionHasNoErrors();
});

test('single scope requires a scalar bill id instead of accepting a combined subset', function (?string $scope) {
    [$consumer, $client, $staff, $bills] = combinedBills();
    $page = route('portal.section', 'payments');
    $this->actingAs($client)->from($page);
    submitCombined($this, $client, '110.00', ['payment_scope' => $scope, 'bill_id' => $bills->pluck('id')->all()])->assertSessionHasErrors('bill_id');
    $this->assertDatabaseCount('payments', 0);
    session()->reflash();
    session()->save();
    $this->get($page)->assertOk();
})->with(['single', null]);

test('invalid array form fields cannot break payment validation retries', function (string $field) {
    [$consumer, $client, $staff, $bills] = combinedBills();
    $page = route('portal.section', 'payments');
    $this->actingAs($client)->from($page);
    submitCombined($this, $client, '1.00', [$field => ['invalid']])->assertSessionHasErrors($field);
    session()->reflash();
    session()->save();
    $this->get($page)->assertOk();
    $this->assertDatabaseCount('payments', 0);
})->with(['payment_scope', 'amount', 'reference', 'paid_at']);
