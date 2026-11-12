<?php

use App\Models\Bill;
use App\Models\BillAdjustment;
use App\Models\Connection;
use App\Models\Consumer;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Models\WaterProvider;
use Illuminate\Support\Facades\Route;

test('provider modules have independent pages and the dashboard only shows summaries', function () {
    $bill = Bill::factory()->create();
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->get(route('dashboard.provider'))->assertOk()->assertSee('Operational attention')->assertSee('Water consumption')->assertDontSee('Register consumer')->assertDontSee('Generate monthly batch');
    foreach (['consumers.index' => 'Consumer Management', 'readings.index' => 'Meter Readings', 'billing.index' => 'Billing Management', 'payments.index' => 'Payment Management', 'balances.index' => 'Outstanding Balance Management', 'service.index' => 'Disconnection &amp; Reconnection', 'reports.billing' => 'Reports', 'profile.edit' => 'Profile Settings'] as $route => $heading) {
        $response = $this->get(route('provider.'.$route))->assertOk()->assertSee($heading, false);
        if ($route === 'reports.billing') $response->assertDontSee('Billing &amp; Consumption Reports', false);
    }
    $this->get(route('provider.consumers.index'))->assertSee('Register consumer')->assertDontSee('Generate monthly batch');
    $this->get(route('provider.payments.index'))->assertSee('Approved bill adjustments')->assertDontSee('Record reading');
    expect(Route::has('provider.periods.store'))->toBeFalse()->and(Route::has('provider.bills.batch'))->toBeFalse();
});

test('billing groups each month together and shows that month\'s consumers together', function () {
    $provider = WaterProvider::factory()->create();
    $octoberConsumer = Consumer::factory()->create(['water_provider_id' => $provider->id, 'name' => 'October Client']);
    $novemberConsumer = Consumer::factory()->create(['water_provider_id' => $provider->id, 'name' => 'November Client']);
    $secondOctoberConsumer = Consumer::factory()->create(['water_provider_id' => $provider->id, 'name' => 'Second October Client']);

    foreach ([[$octoberConsumer, 'OCT-ONE', '2026-10-01'], [$novemberConsumer, 'NOV-ONE', '2026-11-01'], [$secondOctoberConsumer, 'OCT-TWO', '2026-10-01']] as [$consumer, $number, $month]) {
        $connection = Connection::factory()->create(['consumer_id' => $consumer->id, 'water_provider_id' => $provider->id]);
        Bill::factory()->create(['connection_id' => $connection->id, 'consumer_id' => $consumer->id, 'water_provider_id' => $provider->id, 'number' => $number, 'period_month' => $month]);
    }

    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $provider->id]);
    $content = $this->actingAs($staff)->get(route('provider.billing.index'))->assertOk()->getContent();

    expect($content)
        ->toContain('October 2026')
        ->toContain('October Client')
        ->toContain('Second October Client')
        ->toContain('November Client')
        ->and(strpos($content, 'November Client'))->toBeLessThan(strpos($content, 'October Client'));
});

test('billing renders a separate traffic-light payment status column', function () {
    $provider = WaterProvider::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $provider->id]);
    $consumer = Consumer::factory()->create(['water_provider_id' => $provider->id]);
    $paidConnection = Connection::factory()->create(['consumer_id' => $consumer->id, 'water_provider_id' => $provider->id]);
    $partialConnection = Connection::factory()->create(['consumer_id' => $consumer->id, 'water_provider_id' => $provider->id]);
    $unpaidConnection = Connection::factory()->create(['consumer_id' => $consumer->id, 'water_provider_id' => $provider->id]);
    $overdueConnection = Connection::factory()->create(['consumer_id' => $consumer->id, 'water_provider_id' => $provider->id]);
    $voidConnection = Connection::factory()->create(['consumer_id' => $consumer->id, 'water_provider_id' => $provider->id]);

    $paid = Bill::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'connection_id' => $paidConnection->id, 'number' => 'PAID-BILL', 'total' => '100.00']);
    $partial = Bill::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'connection_id' => $partialConnection->id, 'number' => 'PARTIAL-BILL', 'total' => '100.00']);
    Bill::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'connection_id' => $unpaidConnection->id, 'number' => 'UNPAID-BILL', 'total' => '100.00']);
    Bill::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'connection_id' => $overdueConnection->id, 'number' => 'OVERDUE-BILL', 'total' => '100.00', 'due_at' => today()->subDay()]);
    Bill::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'connection_id' => $voidConnection->id, 'number' => 'VOID-BILL', 'status' => 'void']);

    $paidPayment = Payment::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'bill_id' => $paid->id]);
    PaymentAllocation::factory()->create(['payment_id' => $paidPayment->id, 'bill_id' => $paid->id, 'amount' => '100.00']);
    $partialPayment = Payment::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'bill_id' => $partial->id]);
    PaymentAllocation::factory()->create(['payment_id' => $partialPayment->id, 'bill_id' => $partial->id, 'amount' => '50.00']);

    $this->actingAs($staff)->get(route('provider.billing.index'))->assertOk()
        ->assertSee('Status')
        ->assertSee('PAID-BILL')
        ->assertSee('Paid')
        ->assertSee('Partially Paid')
        ->assertSee('Unpaid')
        ->assertSee('Overdue')
        ->assertSee('Void');

    expect(file_get_contents(resource_path('views/provider/billing.blade.php')))
        ->toContain("'Paid' => 'green'")
        ->toContain("'Partially Paid', 'Unpaid' => 'yellow'")
        ->toContain("'Overdue' => 'red'")
        ->toContain("default => 'zinc'")
        ->not->toContain('{{ $bill->number }} · {{ $bill->paymentStatus() }}');
});

test('billing sorts totals and balances from their column headers', function () {
    $provider = WaterProvider::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $provider->id]);

    foreach ([['High total', 'HIGH-TOTAL', '300.00'], ['Low total', 'LOW-TOTAL', '100.00'], ['Middle total', 'MID-TOTAL', '200.00']] as [$name, $number, $total]) {
        $consumer = Consumer::factory()->create(['water_provider_id' => $provider->id, 'name' => $name]);
        $connection = Connection::factory()->create(['consumer_id' => $consumer->id, 'water_provider_id' => $provider->id]);
        Bill::factory()->create(['connection_id' => $connection->id, 'consumer_id' => $consumer->id, 'water_provider_id' => $provider->id, 'number' => $number, 'total' => $total]);
    }

    $content = $this->actingAs($staff)->get(route('provider.billing.index', ['sort' => 'total', 'direction' => 'asc']))->assertOk()->getContent();

    expect($content)
        ->toContain('Sort Total descending')
        ->and(strpos($content, 'LOW-TOTAL'))->toBeLessThan(strpos($content, 'MID-TOTAL'))
        ->and(strpos($content, 'MID-TOTAL'))->toBeLessThan(strpos($content, 'HIGH-TOTAL'));

    $balanceContent = $this->get(route('provider.billing.index', ['sort' => 'balance', 'direction' => 'desc']))->assertOk()->getContent();

    expect($balanceContent)
        ->toContain('Sort Balance ascending')
        ->and(strpos($balanceContent, 'HIGH-TOTAL'))->toBeLessThan(strpos($balanceContent, 'MID-TOTAL'))
        ->and(strpos($balanceContent, 'MID-TOTAL'))->toBeLessThan(strpos($balanceContent, 'LOW-TOTAL'));
});

test('service actions disconnect after two outstanding months and reconnect after settlement', function () {
    $bill = Bill::factory()->create(['due_at' => today()->subMonth(), 'period_month' => '2026-09-01']);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $bill->water_provider_id]);
    $connection = Connection::findOrFail($bill->connection_id);
    $secondConnection = Connection::factory()->create(['water_provider_id' => $bill->water_provider_id, 'consumer_id' => $bill->consumer_id]);
    $secondBill = Bill::factory()->create(['water_provider_id' => $bill->water_provider_id, 'consumer_id' => $bill->consumer_id, 'connection_id' => $secondConnection->id, 'period_month' => '2026-10-01']);
    $url = route('provider.service.store', $connection);

    $this->actingAs($staff)->post($url, ['action' => 'disconnect'])->assertSessionHasErrors('reason');
    $this->assertDatabaseCount('service_events', 0);
    $this->post($url, ['action' => 'disconnect', 'reason' => 'Two unpaid billing months'])->assertSessionHasNoErrors();
    expect($connection->fresh()->status)->toBe('disconnected');
    $bill->update(['total' => '0.00', 'status' => 'paid']);
    $secondBill->update(['total' => '0.00', 'status' => 'paid']);
    $this->post($url, ['action' => 'reconnect'])->assertSessionHasNoErrors();
    expect($connection->fresh()->status)->toBe('active');
    $this->assertDatabaseCount('service_events', 4);
    $this->assertDatabaseHas('service_events', ['connection_id' => $connection->id, 'action' => 'notice', 'reason' => 'Two unpaid billing months']);
    $this->assertDatabaseHas('service_events', ['connection_id' => $connection->id, 'action' => 'disconnect']);
    $this->assertDatabaseHas('service_events', ['connection_id' => $connection->id, 'action' => 'request_reconnection']);
    $this->assertDatabaseHas('service_events', ['connection_id' => $connection->id, 'action' => 'restore']);
});

test('approved credits and debits update balances with an approval history', function () {
    $bill = Bill::factory()->create();
    $admin = User::factory()->superAdmin()->create();
    $url = route('provider.adjustments.store', $bill);

    $this->actingAs($admin)->post($url, ['type' => 'credit', 'amount' => '50.00', 'reason' => 'Correct approved charge'])->assertSessionHasNoErrors();
    expect($bill->fresh()->balance())->toBe('100.00');
    expect(BillAdjustment::firstOrFail()->approved_by)->toBe($admin->id);
    $this->post($url, ['type' => 'credit', 'amount' => '101.00', 'reason' => 'Too large'])->assertSessionHasErrors('amount');
    expect($bill->fresh()->balance())->toBe('100.00');
    $this->post($url, ['type' => 'debit', 'amount' => '20.00', 'reason' => 'Approved additional charge'])->assertSessionHasNoErrors();
    expect($bill->fresh()->balance())->toBe('120.00');
    $this->assertDatabaseCount('bill_adjustments', 2);
    $this->get(route('bills.print', $bill))->assertOk()->assertSee('Correct approved charge');
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $bill->water_provider_id]);
    $this->actingAs($staff)->post($url, ['type' => 'credit', 'amount' => '1.00', 'reason' => 'Not approved'])->assertForbidden();
    $this->assertDatabaseCount('bill_adjustments', 2);
});

test('outstanding balances apply one configured penalty to eligible overdue bills', function () {
    $bill = Bill::factory()->create(['due_at' => today()->subMonth()]);
    $bill->waterProvider->update(['grace_days' => 3, 'late_fee' => '15.00']);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $bill->water_provider_id]);

    $this->actingAs($staff)->post(route('provider.bills.penalty', $bill))->assertSessionHasNoErrors();
    expect($bill->fresh()->balance())->toBe('165.00');
    $this->post(route('provider.bills.penalty', $bill))->assertSessionHasNoErrors();
    expect($bill->fresh()->balance())->toBe('165.00');
    $this->get(route('provider.balances.index'))->assertOk()->assertSee('Penalty')->assertSee('₱15.00');
});

test('outstanding balances put filters below KPIs and use a colour-coded bill table', function () {
    $provider = WaterProvider::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $provider->id]);
    $consumer = Consumer::factory()->create(['water_provider_id' => $provider->id]);
    $connection = Connection::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id]);
    $partialConnection = Connection::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id]);
    $unpaidConnection = Connection::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id]);
    Bill::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'connection_id' => $connection->id, 'due_at' => today()->subDay()]);
    $partial = Bill::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'connection_id' => $partialConnection->id]);
    Bill::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'connection_id' => $unpaidConnection->id]);
    $payment = Payment::factory()->create(['bill_id' => $partial->id, 'water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'reference' => 'PAYMENT-HISTORY']);
    PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'bill_id' => $partial->id, 'amount' => '50.00']);

    $content = $this->actingAs($staff)->get(route('provider.balances.index'))->assertOk()
        ->assertSee('Overdue')->assertSee('Partially Paid')->assertSee('Unpaid')
        ->assertDontSee('Review payments')->assertDontSee('PAYMENT-HISTORY')
        ->assertDontSee('name="from"', false)->assertDontSee('name="to"', false)->getContent();

    $this->get(route('management', 'reports'))->assertOk()
        ->assertSee('name="from"', false)->assertSee('name="to"', false);

    expect(strpos($content, 'Outstanding balance'))->toBeLessThan(strpos($content, 'Consumer name or account'));
    expect(file_get_contents(resource_path('views/provider/summary.blade.php')))
        ->toContain('<table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">')
        ->toContain('<th scope="col" class="px-4 py-3">Name</th>')
        ->toContain('<th scope="col" class="px-4 py-3">Status</th>')
        ->toContain('<th scope="col" class="px-4 py-3">Penalty</th>')
        ->not->toContain('$bill->payments')
        ->toContain('<th scope="col" class="px-4 py-3">Account</th>')
        ->toContain('<th scope="col" class="px-4 py-3">Meter</th>')
        ->toContain('<th scope="col" class="px-4 py-3">Due date</th>')
        ->toContain('<th scope="col" class="px-4 py-3 text-right">Remaining</th>')
        ->toContain("'Overdue' => 'red'")
        ->toContain("'Partially Paid' => 'yellow'")
        ->toContain("'Unpaid' => 'zinc'");
});

test('old consumption reports redirect to the combined report', function () {
    $connection = Connection::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $connection->water_provider_id]);

    $this->actingAs($staff)->get(route('provider.reports.consumption'))->assertRedirect(route('provider.reports.billing'));
});

test('provider navigation exposes only the combined reports module', function () {
    $connection = Connection::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $connection->water_provider_id]);

    $this->actingAs($staff)->get(route('provider.reports.billing'))
        ->assertOk()
        ->assertSee('Reports')
        ->assertDontSee('Billing Reports')
        ->assertDontSee('Water Consumption Reports');
});

test('combined reports exclude void bills', function () {
    $bill = Bill::factory()->create(['period_month' => '2026-09-01', 'total' => '150.00']);
    Bill::factory()->create(['connection_id' => $bill->connection_id, 'meter_reading_id' => $bill->meter_reading_id, 'period_month' => '2026-08-01', 'number' => 'VOID-1', 'status' => 'void', 'total' => '900.00']);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $bill->water_provider_id]);

    $this->actingAs($staff)->get(route('provider.reports.billing', ['range' => 90]))->assertOk()->assertSee('150.00')->assertDontSee('900.00');
});

test('combined billing and consumption report uses billing months and a lookback filter', function () {
    $connection = Connection::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $connection->water_provider_id]);
    MeterReading::factory()->create(['connection_id' => $connection->id, 'water_provider_id' => $connection->water_provider_id, 'period_month' => '2026-08-01', 'previous_reading' => '0.000', 'current_reading' => '10.000']);
    MeterReading::factory()->create(['connection_id' => $connection->id, 'water_provider_id' => $connection->water_provider_id, 'period_month' => '2026-09-01', 'previous_reading' => '10.000', 'current_reading' => '35.000']);
    $bill = Bill::factory()->create(['connection_id' => $connection->id, 'water_provider_id' => $connection->water_provider_id, 'consumer_id' => $connection->consumer_id, 'period_month' => '2026-09-01', 'total' => '150.00']);
    $payment = Payment::factory()->create(['water_provider_id' => $connection->water_provider_id, 'consumer_id' => $connection->consumer_id, 'bill_id' => $bill->id, 'amount' => '50.00', 'status' => 'verified']);
    PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'bill_id' => $bill->id, 'amount' => '50.00']);

    $this->actingAs($staff)->get(route('provider.reports.billing', ['range' => 90]))
        ->assertOk()
        ->assertSee('Reports')
        ->assertSee('Last 90 days')
        ->assertDontSee('name="from"', false)
        ->assertDontSee('name="to"', false)
        ->assertSee('Sep 2026')
        ->assertSee('25.000')
        ->assertSee('1 high')
        ->assertSee('150.00')
        ->assertSee('50.00')
        ->assertSee('100.00');
});

test('reports use an exact month filter for the page, CSV, and printable report', function () {
    $connection = Connection::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $connection->water_provider_id]);
    $septemberReading = MeterReading::factory()->create(['connection_id' => $connection->id, 'water_provider_id' => $connection->water_provider_id, 'period_month' => '2026-09-01', 'previous_reading' => '0.000', 'current_reading' => '10.000']);
    $octoberReading = MeterReading::factory()->create(['connection_id' => $connection->id, 'water_provider_id' => $connection->water_provider_id, 'period_month' => '2026-10-01', 'previous_reading' => '10.000', 'current_reading' => '40.000']);
    Bill::factory()->create(['connection_id' => $connection->id, 'consumer_id' => $connection->consumer_id, 'water_provider_id' => $connection->water_provider_id, 'meter_reading_id' => $septemberReading->id, 'period_month' => '2026-09-01', 'total' => '111.00']);
    Bill::factory()->create(['connection_id' => $connection->id, 'consumer_id' => $connection->consumer_id, 'water_provider_id' => $connection->water_provider_id, 'meter_reading_id' => $octoberReading->id, 'period_month' => '2026-10-01', 'total' => '999.00']);
    $filter = ['range' => 90, 'month' => '2026-09'];

    $this->actingAs($staff)->get(route('provider.reports.billing', $filter))
        ->assertOk()
        ->assertSee('name="month"', false)
        ->assertSee('value="2026-09"', false)
        ->assertSee('Sep 2026')
        ->assertDontSee('Oct 2026')
        ->assertSee('111.00')
        ->assertDontSee('999.00');

    $csv = $this->get(route('provider.reports.billing.csv', $filter));
    expect($csv->streamedContent())->toContain('2026-09')->not->toContain('2026-10')->not->toContain('999.00');
    $this->get(route('provider.reports.billing.print', $filter))->assertOk()->assertSee('Billing month')->assertSee('Sep 2026')->assertDontSee('Oct 2026');
});

test('reports reject invalid month filters', function () {
    $connection = Connection::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $connection->water_provider_id]);

    $this->actingAs($staff)->get(route('provider.reports.billing', ['month' => '2026-13']))->assertSessionHasErrors('month');
});

test('combined report exports CSV and printable records', function () {
    $bill = Bill::factory()->create(['period_month' => '2026-09-01', 'number' => 'EXPORT-BILL']);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $bill->water_provider_id]);

    $csv = $this->actingAs($staff)->get(route('provider.reports.billing.csv', ['range' => 90]));
    $csv
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($csv->streamedContent())->toContain('Billing month')->toContain('2026-09');
    $this->get(route('provider.reports.billing.print', ['range' => 90, 'preview' => 1]))
        ->assertOk()
        ->assertSee('Billing &amp; Consumption Report', false)
        ->assertSee('data-print-document', false);
});

test('consumers cannot access provider modules or change service status', function () {
    $connection = Connection::factory()->create();
    $consumer = User::factory()->create(['consumer_id' => $connection->consumer_id]);

    $this->actingAs($consumer)->get(route('provider.service.index'))->assertForbidden();
    $this->get(route('provider.payments.index'))->assertForbidden();
    $this->post(route('provider.service.store', $connection), ['action' => 'notice', 'reason' => 'Unauthorized'])->assertForbidden();
    $this->assertDatabaseCount('service_events', 0);
});

test('provider profile saves office details and provider-specific penalty settings', function () {
    $connection = Connection::factory()->create();
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->put(route('provider.profile'), ['name' => 'Town Water', 'address' => 'Municipal Office', 'phone' => '09123456789', 'email' => 'office@example.com', 'due_days' => 15, 'grace_days' => 5, 'late_fee' => '20.00'])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('water_providers', ['id' => $connection->water_provider_id, 'name' => 'Town Water', 'address' => 'Municipal Office', 'grace_days' => 5, 'late_fee' => '20.00']);
    $this->get(route('provider.profile.edit'))->assertOk()->assertSee('Municipal Office');
});

test('provider consumer contacts require digits-only phone numbers', function () {
    $connection = Connection::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $connection->water_provider_id]);

    $this->actingAs($staff)->post(route('provider.consumers.store'), [
        'account_number' => 'A-100', 'name' => 'Client', 'address' => 'North Street', 'phone' => '0912-ABC',
    ])->assertSessionHasErrors('phone');
});

test('registering a consumer also creates their first connection', function () {
    $provider = \App\Models\WaterProvider::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $provider->id]);

    $this->actingAs($staff)->post(route('provider.consumers.store'), [
        'account_number' => 'A-200', 'name' => 'New Client', 'address' => 'North Street',
        'number' => 'CON-200', 'meter_number' => 'MTR-200', 'installed_at' => '2026-11-04 00:00:00',
    ])->assertSessionHasNoErrors();

    $consumer = \App\Models\Consumer::where('account_number', 'A-200')->firstOrFail();
    $this->assertDatabaseHas('connections', [
        'water_provider_id' => $provider->id, 'consumer_id' => $consumer->id,
        'number' => 'CON-200', 'meter_number' => 'MTR-200', 'installed_at' => '2026-11-04 00:00:00',
    ]);
});

test('registering a consumer generates connection and meter numbers when blank', function () {
    $provider = WaterProvider::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $provider->id]);

    $this->actingAs($staff)->post(route('provider.consumers.store'), [
        'account_number' => 'A-AUTO', 'name' => 'Generated Client', 'address' => 'North Street',
        'number' => '', 'meter_number' => '',
    ])->assertSessionHasNoErrors();

    $connection = Consumer::where('account_number', 'A-AUTO')->firstOrFail()->connections()->sole();
    expect($connection->number)->toStartWith('CN-')
        ->and($connection->meter_number)->toStartWith('MTR-');
});

test('editing a consumer updates its connection fields and generates blank identifiers', function () {
    $connection = Connection::factory()->create(['number' => 'CON-OLD', 'meter_number' => 'MTR-OLD']);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $connection->water_provider_id]);

    $this->actingAs($staff)->get(route('provider.consumers.index', ['edit_consumer' => $connection->consumer_id]))
        ->assertOk()
        ->assertSee('Account number')
        ->assertSee('Connection number (leave blank to generate)')
        ->assertSee('Meter number (leave blank to generate)')
        ->assertSee('Installation date (optional)');

    $this->actingAs($staff)->put(route('provider.consumers.update', $connection->consumer), [
        'account_number' => $connection->consumer->account_number,
        'name' => 'Updated Client',
        'address' => 'Updated Street',
        'number' => '', 'meter_number' => '', 'installed_at' => '2026-11-01',
    ])->assertSessionHasNoErrors();

    expect($connection->fresh()->number)->toStartWith('CN-')
        ->not->toBe('CON-OLD')
        ->and($connection->fresh()->meter_number)->toStartWith('MTR-')
        ->not->toBe('MTR-OLD')
        ->and($connection->fresh()->installed_at->toDateString())->toBe('2026-11-01');
});

test('reconnection checks the consumer balance across all their connections', function () {
    $bill = Bill::factory()->create();
    $connection = Connection::factory()->create(['consumer_id' => $bill->consumer_id, 'water_provider_id' => $bill->water_provider_id, 'status' => 'disconnected']);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $bill->water_provider_id]);

    $this->actingAs($staff)->post(route('provider.service.store', $connection), ['action' => 'request_reconnection', 'reason' => 'Request for second meter'])->assertSessionHasErrors('action');

    expect($connection->fresh()->status)->toBe('disconnected');
    $this->assertDatabaseCount('service_events', 0);
});
