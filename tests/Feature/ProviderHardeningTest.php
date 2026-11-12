<?php

use App\Actions\PaymentService;
use App\Models\Bill;
use App\Models\Connection;
use App\Models\Consumer;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\RolePermission;
use App\Models\ServiceEvent;
use App\Models\User;
use App\Models\WaterProvider;
use App\UserRole;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

test('consumer pages are paginated have descriptive titles and do not query unrelated modules', function () {
    $provider = WaterProvider::factory()->create();
    Consumer::factory()->count(23)->create(['water_provider_id' => $provider->id]);
    $admin = User::factory()->superAdmin()->create();
    DB::enableQueryLog();

    $response = $this->actingAs($admin)->get(route('provider.consumers.index'));

    $response->assertOk()->assertSee('Consumer Management -')->assertViewHas('consumers', fn ($consumers) => $consumers->total() === 23 && $consumers->count() === 20);
    $queries = collect(DB::getQueryLog())->pluck('query')->implode(' ');
    expect($queries)->not->toContain('from "bills"')->not->toContain('from "payments"')->not->toContain('from "meter_readings"');
    expect(substr_count($response->getContent(), 'data-modal="edit-consumer-'))->toBe(0);
});

test('only the selected consumer edit form is rendered and submitted values survive validation', function () {
    $consumer = Consumer::factory()->create();
    $other = Consumer::factory()->create(['water_provider_id' => $consumer->water_provider_id]);
    $admin = User::factory()->superAdmin()->create();
    $page = route('provider.consumers.index', ['edit_consumer' => $consumer->id]);

    $this->actingAs($admin)->get($page)->assertOk()->assertSee('data-modal="edit-consumer-'.$consumer->id.'"', false)->assertDontSee('data-modal="edit-consumer-'.$other->id.'"', false)->assertSee('initiallyOpen: true', false);
    $this->from($page)->put(route('provider.consumers.update', $consumer), ['_form' => 'edit-consumer-'.$consumer->id, 'name' => 'Unsaved revised name', 'email' => 'invalid', 'phone' => '09123456789', 'address' => 'Revised address'])->assertSessionHasErrors('email');
    $this->get($page)->assertOk()->assertSee('value="Unsaved revised name"', false)->assertSee('value="Revised address"', false);
    expect($consumer->fresh()->name)->toBe($consumer->name);
});

test('consumer details render provider-scoped filtered account activity with receipts', function () {
    $provider = WaterProvider::factory()->create();
    $consumer = Consumer::factory()->create(['water_provider_id' => $provider->id, 'name' => 'CONSUMER-PREVIEW', 'account_number' => 'ACCOUNT-PREVIEW']);
    $connection = Connection::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'meter_number' => 'METER-PREVIEW']);
    $secondConnection = Connection::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'meter_number' => 'METER-SECOND']);
    $voidConnection = Connection::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'meter_number' => 'METER-VOID']);
    $octoberReading = MeterReading::factory()->create(['water_provider_id' => $provider->id, 'connection_id' => $connection->id, 'period_month' => '2026-10-01', 'previous_reading' => '10.000', 'current_reading' => '24.000']);
    $secondOctoberReading = MeterReading::factory()->create(['water_provider_id' => $provider->id, 'connection_id' => $secondConnection->id, 'period_month' => '2026-10-01', 'previous_reading' => '4.000', 'current_reading' => '24.000']);
    $voidOctoberReading = MeterReading::factory()->create(['water_provider_id' => $provider->id, 'connection_id' => $voidConnection->id, 'period_month' => '2026-09-01']);
    MeterReading::factory()->create(['water_provider_id' => $provider->id, 'connection_id' => $connection->id, 'period_month' => '2026-09-01', 'previous_reading' => '0.000', 'current_reading' => '7.000']);
    $bill = Bill::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'connection_id' => $connection->id, 'meter_reading_id' => $octoberReading->id, 'number' => 'BILL-PREVIEW', 'period_month' => '2026-10-01', 'total' => '150.00']);
    $secondBill = Bill::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'connection_id' => $secondConnection->id, 'meter_reading_id' => $secondOctoberReading->id, 'number' => 'BILL-SECOND', 'period_month' => '2026-10-01', 'total' => '100.00']);
    $voidBill = Bill::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'connection_id' => $voidConnection->id, 'meter_reading_id' => $voidOctoberReading->id, 'number' => 'BILL-VOID', 'period_month' => '2026-10-01', 'total' => '150.00', 'status' => 'void']);
    $payment = Payment::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'bill_id' => $bill->id, 'reference' => 'PAY-PREVIEW', 'amount' => '50.00', 'status' => 'verified', 'paid_at' => '2026-10-15']);
    PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'bill_id' => $bill->id, 'amount' => '50.00']);
    $secondPayment = Payment::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'bill_id' => $secondBill->id, 'amount' => '25.00', 'status' => 'verified', 'paid_at' => '2026-10-18']);
    PaymentAllocation::factory()->create(['payment_id' => $secondPayment->id, 'bill_id' => $secondBill->id, 'amount' => '25.00']);
    Payment::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'bill_id' => $bill->id, 'reference' => 'PAY-PENDING', 'status' => 'pending', 'paid_at' => '2026-10-16']);
    Payment::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'bill_id' => $bill->id, 'reference' => 'PAY-REJECTED', 'status' => 'rejected', 'paid_at' => '2026-10-17']);
    Payment::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'bill_id' => $voidBill->id, 'reference' => 'PAY-VOID', 'status' => 'pending', 'paid_at' => '2026-10-18']);
    Payment::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'bill_id' => $bill->id, 'reference' => 'PAY-NEXT-MONTH', 'paid_at' => '2026-11-01']);
    $otherConsumer = Consumer::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $provider->id]);

    $this->actingAs($staff)->get(route('provider.consumers.index', ['preview_consumer' => $consumer->id, 'period' => '2026-10']))
        ->assertOk()
        ->assertSee('Client history')
        ->assertDontSee('Back to consumers')
        ->assertSeeInOrder(['Consumer Management', 'CONSUMER-PREVIEW', 'ACCOUNT-PREVIEW', 'Client history'])
        ->assertDontSee('Search consumer name or account')
        ->assertDontSee('data-modal="consumer-preview-'.$consumer->id.'"', false)
        ->assertSee('Outstanding balance')
        ->assertSee('Latest activity')
        ->assertSee('Nov 01, 2026')
        ->assertSee('Monthly account report')
        ->assertDontSee('Account activity')
        ->assertViewHas('monthlyReports', fn ($reports) => $reports->count() === 1 && $reports->first()['meters'] === ['METER-PREVIEW', 'METER-SECOND'])
        ->assertSee('METER-SECOND')
        ->assertSee('34.000 m³')
        ->assertSee('₱250.00')
        ->assertSee('₱75.00')
        ->assertSee('₱175.00')
        ->assertSee('2 pending')
        ->assertDontSee('3 pending')
        ->assertSee('1 rejected')
        ->assertSee('name="period"', false)
        ->assertDontSee('Meter-reading history')
        ->assertDontSee('Bill history')
        ->assertDontSee('Payment history')
        ->assertSee('ACCOUNT-PREVIEW')
        ->assertSee('METER-PREVIEW')
        ->assertSee('BILL-PREVIEW')
        ->assertSee('BILL-SECOND')
        ->assertDontSee('BILL-VOID')
        ->assertDontSee('PAY-PREVIEW')
        ->assertDontSee('PAY-PENDING')
        ->assertDontSee('PAY-REJECTED')
        ->assertDontSee('PAY-VOID')
        ->assertDontSee('PAY-NEXT-MONTH')
        ->assertDontSee(route('payments.print', $payment), false)
        ->assertSee(route('bills.print', $bill), false)
        ->assertSee(route('bills.print', $secondBill), false);

    $this->get(route('provider.consumers.index', ['preview_consumer' => $consumer->id]))
        ->assertOk()
        ->assertSee('Bill pending');

    $this->get(route('provider.consumers.index', ['preview_consumer' => $otherConsumer->id]))->assertNotFound();
});

test('consumer monthly report does not expose bills to payment-only staff', function () {
    $provider = WaterProvider::factory()->create();
    $consumer = Consumer::factory()->create(['water_provider_id' => $provider->id]);
    $connection = Connection::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'meter_number' => 'METER-PAYMENTS-ONLY']);
    $bill = Bill::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'connection_id' => $connection->id, 'number' => 'BILL-PRIVATE', 'period_month' => '2026-10-01', 'total' => '150.00']);
    Payment::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'bill_id' => $bill->id, 'status' => 'pending']);
    RolePermission::factory()->create(['role' => UserRole::WaterProvider->value, 'permission' => 'billing.view', 'allowed' => false]);
    RolePermission::factory()->create(['role' => UserRole::WaterProvider->value, 'permission' => 'connections.view', 'allowed' => false]);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $provider->id]);

    $this->actingAs($staff)->get(route('provider.consumers.index', ['preview_consumer' => $consumer->id, 'period' => '2026-10']))
        ->assertOk()
        ->assertSee('1 pending')
        ->assertSee('Payment activity')
        ->assertDontSee('Bill pending')
        ->assertDontSee('METER-PAYMENTS-ONLY')
        ->assertDontSee('BILL-PRIVATE')
        ->assertDontSee('₱150.00')
        ->assertDontSee(route('bills.print', $bill), false);
});

test('consumer actions include a view link to the account details', function () {
    $consumer = Consumer::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $consumer->water_provider_id]);

    $this->actingAs($staff)->get(route('provider.consumers.index'))
        ->assertOk()
        ->assertSee('View')
        ->assertSee('text-zinc-800', false)
        ->assertDontSee('text-brand-primary no-underline', false)
        ->assertSee(route('provider.consumers.index', ['preview_consumer' => $consumer->id]), false);
});

test('providers can send password reset links only to their consumer portal accounts', function () {
    Notification::fake();
    $provider = WaterProvider::factory()->create();
    $consumer = Consumer::factory()->create(['water_provider_id' => $provider->id]);
    $portalUser = User::factory()->create(['consumer_id' => $consumer->id]);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $provider->id]);
    $otherConsumer = Consumer::factory()->create();
    $otherPortalUser = User::factory()->create(['consumer_id' => $otherConsumer->id]);

    $this->actingAs($staff)->post(route('provider.consumers.password-reset', $consumer))
        ->assertRedirect()
        ->assertSessionHas('status', 'Password reset link sent.');

    Notification::assertSentTo($portalUser, ResetPassword::class);
    $this->post(route('provider.consumers.password-reset', $otherConsumer))->assertNotFound();
    Notification::assertNotSentTo($otherPortalUser, ResetPassword::class);
});

test('meter readings retain their current value after a lower later reading is rejected', function () {
    $connection = Connection::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $connection->water_provider_id]);
    $page = route('provider.readings.index');

    MeterReading::factory()->create(['connection_id' => $connection->id, 'water_provider_id' => $connection->water_provider_id, 'period_month' => '2026-09-01', 'current_reading' => '100.000']);

    $this->actingAs($staff)->from($page)->post(route('provider.readings.store'), ['_form' => 'create-reading', 'connection_id' => $connection->id, 'period_month' => '2026-10', 'current_reading' => '90.000'])->assertSessionHasErrors('current_reading');
    session()->reflash();
    session()->save();
    $this->get($page)->assertOk()->assertSee('value="90.000"', false);
    $this->assertDatabaseCount('meter_readings', 1);
});

test('recorded meter readings appear as pending and use one editable reading field', function () {
    $connection = Connection::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $connection->water_provider_id]);
    $page = route('provider.readings.index');

    $this->actingAs($staff)->get($page)
        ->assertOk()
        ->assertSee('Record meter reading')
        ->assertSeeInOrder(['Billing month', 'Reading'])
        ->assertDontSee('Current meter reading')
        ->assertSee('name="current_reading"', false);

    $this->from($page)->post(route('provider.readings.store'), [
        '_form' => 'create-reading',
        'connection_id' => $connection->id,
        'period_month' => '2026-01',
        'current_reading' => '12.000',
    ])->assertSessionHasNoErrors();

    $this->get($page)->assertOk()
        ->assertSee($connection->consumer->name)
        ->assertSee('Pending');
});

test('bill generation explains the blank connection batch action beside its controls', function () {
    $provider = WaterProvider::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $provider->id]);

    $this->actingAs($staff)->get(route('provider.billing.index'))
        ->assertOk()
        ->assertSee('No connection selected? Bills will be generated for all eligible unbilled connections.')
        ->assertSee('class="flex flex-wrap justify-end gap-2 sm:ms-auto"', false);
});

test('payment management separates original bills and payment receipts from audit dates and attachments', function () {
    $provider = WaterProvider::factory()->create();
    $consumer = Consumer::factory()->create(['water_provider_id' => $provider->id]);
    $connection = Connection::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id]);
    $bill = Bill::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'connection_id' => $connection->id]);
    $payment = Payment::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'bill_id' => $bill->id, 'paid_at' => '2026-11-01', 'proof_path' => 'payment-proofs/consumer-proof.pdf']);
    $verifiedPayment = Payment::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'bill_id' => $bill->id, 'status' => 'verified', 'paid_at' => '2026-11-02', 'checked_at' => '2026-11-03 10:15:00']);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $provider->id]);

    $this->actingAs($staff)->get(route('provider.payments.index'))
        ->assertOk()
        ->assertSee('Payment date')
        ->assertSee('Date checked')
        ->assertSee('Original bill')
        ->assertSee('Payment receipt')
        ->assertSee('Attachment')
        ->assertSee('Nov 01, 2026')
        ->assertSee('Nov 03, 2026 10:15 AM')
        ->assertSee('View attachment')
        ->assertSee('View payment receipt')
        ->assertSee(route('payments.proof', $payment, false), false)
        ->assertSee(route('bills.print', $bill, false), false)
        ->assertDontSee('role="link" tabindex="0"', false);
});

test('payment review records a checked date when verified or rejected', function () {
    $provider = WaterProvider::factory()->create();
    $consumer = Consumer::factory()->create(['water_provider_id' => $provider->id]);
    $connection = Connection::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id]);
    $bill = Bill::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'connection_id' => $connection->id, 'total' => '200.00']);
    $verified = Payment::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'bill_id' => $bill->id, 'amount' => '50.00']);
    $rejected = Payment::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'bill_id' => $bill->id, 'amount' => '25.00']);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $provider->id]);

    app(PaymentService::class)->verify($verified, $staff);
    $this->actingAs($staff)->post(route('provider.payments.reject', $rejected), ['remarks' => 'Proof could not be confirmed.'])->assertSessionHasNoErrors();

    expect($verified->fresh()->checked_at)->not->toBeNull()
        ->and($rejected->fresh()->checked_at)->not->toBeNull();
});

test('locked readings and ineligible service actions are explained instead of offered', function () {
    $bill = Bill::factory()->create(['due_at' => today()->addMonth()]);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $bill->water_provider_id]);

    $this->actingAs($staff)->get(route('provider.readings.index'))->assertOk()->assertSee('Bill Generated')->assertDontSee('action="'.route('provider.readings.update', $bill->meter_reading_id).'"', false);
    $this->get(route('provider.service.index'))
        ->assertOk()
        ->assertSee('Two outstanding billing months are required before disconnection.')
        ->assertSee('aria-label="Service actions"', false)
        ->assertSee('View bills')
        ->assertDontSee('<th scope="col" class="px-4 py-3">Account bills</th>', false);
});

test('meter reading history presents its readings in a table', function () {
    $reading = \App\Models\MeterReading::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $reading->water_provider_id]);

    $this->actingAs($staff)->get(route('provider.readings.index'))
        ->assertOk()
        ->assertDontSee('Initial previous reading (first reading only)')
        ->assertDontSee('>Previous<', false)
        ->assertDontSee('>Current<', false)
        ->assertSee('Usage (m³)')
        ->assertDontSee('Correction')
        ->assertDontSee('Correct reading');
});

test('meter readings show provider-wide KPIs and consumption trend', function () {
    $this->travelTo('2026-11-05');
    $provider = WaterProvider::factory()->create();
    $activeConsumer = Consumer::factory()->create(['water_provider_id' => $provider->id]);
    $activeConnection = Connection::factory()->create(['consumer_id' => $activeConsumer->id, 'status' => 'active']);
    $inactiveConsumer = Consumer::factory()->create(['water_provider_id' => $provider->id]);
    $inactiveConnection = Connection::factory()->create(['consumer_id' => $inactiveConsumer->id, 'status' => 'disconnected']);
    MeterReading::factory()->create(['connection_id' => $activeConnection->id, 'period_month' => '2026-10-01', 'previous_reading' => '1.000', 'current_reading' => '6.000']);
    MeterReading::factory()->create(['connection_id' => $inactiveConnection->id, 'period_month' => '2026-11-01', 'previous_reading' => '2.000', 'current_reading' => '9.000']);
    MeterReading::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $provider->id]);

    $response = $this->actingAs($staff)->get(route('provider.readings.index', ['search' => 'No matching consumer', 'period' => '2026-10']));

    $response->assertOk()
        ->assertSee('Recorded readings')
        ->assertSee('Active connections')
        ->assertSee('Not Active connections')
        ->assertSee('Water consumption')
        ->assertSee('data-meter-reading-summary', false)
        ->assertDontSee('View monthly values')
        ->assertViewHas('recordedReadingCount', 2)
        ->assertViewHas('activeConnectionCount', 1)
        ->assertViewHas('inactiveConnectionCount', 1);
    expect(collect($response->viewData('consumptionTrend'))->firstWhere('month', 'Oct 2026')['consumption'])->toBe('5.000');
    expect(collect($response->viewData('consumptionTrend'))->firstWhere('month', 'Nov 2026')['consumption'])->toBe('7.000');
});

test('meter readings link consumers and generated bills while pending readings remain informational', function () {
    $bill = Bill::factory()->create();
    $pendingConsumer = Consumer::factory()->create(['water_provider_id' => $bill->water_provider_id]);
    $pendingConnection = Connection::factory()->create(['consumer_id' => $pendingConsumer->id]);
    MeterReading::factory()->create(['connection_id' => $pendingConnection->id]);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $bill->water_provider_id]);

    $this->actingAs($staff)->get(route('provider.readings.index'))
        ->assertOk()
        ->assertSee(route('provider.consumers.index', ['search' => $bill->consumer->account_number]), false)
        ->assertSee(route('provider.payments.index', ['bill' => $bill->id]), false)
        ->assertSee('Bill Generated')
        ->assertSee('Pending');
});

test('payment management shows the selected bill and only its payments', function () {
    $bill = Bill::factory()->create();
    $connection = Connection::findOrFail($bill->connection_id);
    $payment = Payment::factory()->create(['bill_id' => $bill->id, 'reference' => 'PAY-SELECTED']);
    $otherConsumer = Consumer::factory()->create(['water_provider_id' => $bill->water_provider_id]);
    $otherConnection = Connection::factory()->create(['consumer_id' => $otherConsumer->id]);
    $otherBill = Bill::factory()->create(['connection_id' => $otherConnection->id]);
    Payment::factory()->create(['bill_id' => $otherBill->id, 'reference' => 'PAY-OTHER']);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $bill->water_provider_id]);

    $this->actingAs($staff)->get(route('provider.payments.index', ['bill' => $bill->id]))
        ->assertOk()
        ->assertSee($bill->number)
        ->assertSee($connection->meter_number)
        ->assertSee('name="bill" value="'.$bill->id.'"', false)
        ->assertSee($payment->reference)
        ->assertDontSee('PAY-OTHER');
});

test('payment management rejects a selected bill from another provider', function () {
    $bill = Bill::factory()->create();
    $otherBill = Bill::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $bill->water_provider_id]);

    $this->actingAs($staff)->get(route('provider.payments.index', ['bill' => $otherBill->id]))
        ->assertRedirect()
        ->assertSessionHasErrors('bill');
});

test('search applies to connection tables and service history consistently', function () {
    $connection = Connection::factory()->create();
    $connection->consumer->update(['name' => 'Ada Rivera']);
    $otherConsumer = Consumer::factory()->create(['water_provider_id' => $connection->water_provider_id, 'name' => 'Other Consumer']);
    $other = Connection::factory()->create(['water_provider_id' => $connection->water_provider_id, 'consumer_id' => $otherConsumer->id]);
    ServiceEvent::factory()->create(['connection_id' => $other->id, 'reason' => 'Unrelated history']);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $connection->water_provider_id]);

    $this->actingAs($staff)->get(route('provider.readings.index', ['search' => 'Ada']))->assertOk()->assertSee('Ada Rivera')->assertDontSee('Other Consumer');
    $this->get(route('provider.service.index', ['search' => 'Ada']))->assertOk()->assertSee('Ada Rivera')->assertDontSee('Unrelated history');
});

test('provider pages render one shared validation summary', function () {
    WaterProvider::factory()->create();
    $admin = User::factory()->superAdmin()->create();
    $errors = (new ViewErrorBag)->put('default', new MessageBag(['name' => 'Enter the registered name.']));

    $this->actingAs($admin)->withSession(['errors' => $errors]);
    session()->save();
    $response = $this->get(route('provider.consumers.index'));

    $response->assertOk();
    expect(substr_count($response->getContent(), 'data-form-feedback='))->toBe(1);
});

test('connection operations show provider-scoped financial and service context', function () {
    $provider = WaterProvider::factory()->create();
    $consumer = Consumer::factory()->create(['water_provider_id' => $provider->id]);
    $active = Connection::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'status' => 'active']);
    Connection::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'status' => 'disconnected']);
    Connection::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'status' => 'pending_reconnection']);
    $bill = Bill::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'connection_id' => $active->id, 'due_at' => today()->subDay(), 'total' => '100.00']);
    Payment::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'bill_id' => $bill->id, 'status' => 'pending', 'reference' => 'PENDING-PROVIDER']);

    $otherBill = Bill::factory()->create(['due_at' => today()->subDay(), 'total' => '900.00']);
    Payment::factory()->create(['bill_id' => $otherBill->id, 'status' => 'pending', 'reference' => 'PENDING-OTHER']);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $provider->id]);

    $this->actingAs($staff)->get(route('provider.balances.index'))
        ->assertOk()
        ->assertViewHas('billCount', 1)
        ->assertViewHas('overdueBillCount', 1)
        ->assertSee('Outstanding balance')
        ->assertDontSee('Review payments')
        ->assertDontSee('PENDING-PROVIDER')
        ->assertDontSee('900.00');

    $this->get(route('provider.service.index'))
        ->assertOk()
        ->assertViewHas('serviceStatusCounts', ['active' => 1, 'disconnected' => 1, 'pending_reconnection' => 1])
        ->assertSee('Service lifecycle');

    $this->get(route('provider.payments.index'))
        ->assertOk()
        ->assertViewHas('pendingPaymentCount', 1)
        ->assertSee('Pending review')
        ->assertSee($bill->number)
        ->assertSee($active->meter_number)
        ->assertDontSee('PENDING-OTHER');
});

test('service records render in connection and history tables', function () {
    $connection = Connection::factory()->create();
    Bill::factory()->create(['water_provider_id' => $connection->water_provider_id, 'consumer_id' => $connection->consumer_id, 'connection_id' => $connection->id, 'due_at' => today()->subDay(), 'period_month' => '2026-09-01']);
    $secondConnection = Connection::factory()->create(['water_provider_id' => $connection->water_provider_id, 'consumer_id' => $connection->consumer_id]);
    Bill::factory()->create(['water_provider_id' => $connection->water_provider_id, 'consumer_id' => $connection->consumer_id, 'connection_id' => $secondConnection->id, 'period_month' => '2026-10-01']);
    Connection::factory()->create(['water_provider_id' => $connection->water_provider_id, 'status' => 'disconnected']);
    ServiceEvent::factory()->create(['connection_id' => $connection->id]);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $connection->water_provider_id]);

    $response = $this->actingAs($staff)->get(route('provider.service.index'));

    $response
        ->assertOk()
        ->assertSee('<th scope="col" class="px-4 py-3 text-right">Account-wide balance</th>', false)
        ->assertSee('Disconnection Needed')
        ->assertSee('aria-label="Service actions"', false)
        ->assertSee('Disconnect')
        ->assertSee('View bills')
        ->assertSee('bg-green-400/20', false)
        ->assertSee('bg-red-400/20', false)
        ->assertDontSee('Reason / notice details')
        ->assertSee('disconnect-'.$connection->id, false)
        ->assertSee('Service history')
        ->assertSee('<th scope="col" class="px-4 py-3">Recorded</th>', false);

    expect(substr_count($response->getContent(), '<table'))->toBe(2);
});
