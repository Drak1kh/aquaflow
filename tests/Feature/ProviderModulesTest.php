<?php

use App\Models\Bill;
use App\Models\BillAdjustment;
use App\Models\Connection;
use App\Models\Consumer;
use App\Models\MeterReading;
use App\Models\User;
use App\Models\WaterProvider;
use Illuminate\Support\Facades\Route;

test('provider modules have independent pages and the dashboard only shows summaries', function () {
    $bill = Bill::factory()->create();
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->get(route('dashboard.provider'))->assertOk()->assertSee('Operational attention')->assertSee('Water consumption')->assertDontSee('Register consumer')->assertDontSee('Generate monthly batch');
    foreach (['consumers.index' => 'Consumer Management', 'readings.index' => 'Meter Readings', 'billing.index' => 'Billing Management', 'payments.index' => 'Payment Management', 'balances.index' => 'Outstanding Balance Management', 'service.index' => 'Disconnection &amp; Reconnection', 'reports.billing' => 'Billing Reports', 'reports.consumption' => 'Water Consumption Reports', 'profile.edit' => 'Profile Settings'] as $route => $heading) {
        $this->get(route('provider.'.$route))->assertOk()->assertSee($heading, false);
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

test('service changes require notice overdue balance settlement and an explicit restoration', function () {
    $bill = Bill::factory()->create(['due_at' => today()->subMonth()]);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $bill->water_provider_id]);
    $connection = Connection::findOrFail($bill->connection_id);
    $url = route('provider.service.store', $connection);

    $this->actingAs($staff)->post($url, ['action' => 'disconnect', 'reason' => 'Overdue'])->assertSessionHasErrors('action');
    $this->assertDatabaseCount('service_events', 0);
    $this->post($url, ['action' => 'notice', 'reason' => 'Notice delivered'])->assertSessionHasNoErrors();
    $this->post($url, ['action' => 'disconnect', 'reason' => 'Service disconnected'])->assertSessionHasNoErrors();
    expect($connection->fresh()->status)->toBe('disconnected');
    $this->post($url, ['action' => 'request_reconnection', 'reason' => 'Request'])->assertSessionHasErrors('action');
    $bill->update(['total' => '0.00', 'status' => 'paid']);
    expect($connection->fresh()->status)->toBe('disconnected');
    $this->post($url, ['action' => 'restore', 'reason' => 'Restore'])->assertSessionHasErrors('action');
    $this->post($url, ['action' => 'request_reconnection', 'reason' => 'Balance cleared'])->assertSessionHasNoErrors();
    expect($connection->fresh()->status)->toBe('pending_reconnection');
    $this->post($url, ['action' => 'restore', 'reason' => 'Restoration completed'])->assertSessionHasNoErrors();
    expect($connection->fresh()->status)->toBe('active');
    $this->assertDatabaseCount('service_events', 4);
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
    $this->get(route('provider.balances.index'))->assertOk()->assertSee('Penalty ₱15.00');
});

test('consumption reports compare earlier periods even when filtering to one month', function () {
    $connection = Connection::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $connection->water_provider_id]);
    foreach ([['2026-08-01', '0', '10'], ['2026-09-01', '10', '35'], ['2026-10-01', '35', '40']] as [$month, $previous, $current]) {
        MeterReading::factory()->create(['connection_id' => $connection->id, 'water_provider_id' => $connection->water_provider_id, 'period_month' => $month, 'previous_reading' => $previous, 'current_reading' => $current]);
    }

    $this->actingAs($staff)->get(route('provider.reports.consumption', ['month' => '2026-09']))->assertOk()->assertSee('Unusually high')->assertSee('25.000')->assertSee('10.000');
    $this->get(route('provider.reports.consumption', ['month' => '2026-10']))->assertOk()->assertSee('Unusually low')->assertSee('5.000');
});

test('billing reports group generated charges daily monthly and yearly and exclude void bills', function () {
    $bill = Bill::factory()->create(['created_at' => '2026-09-05 10:00:00']);
    Bill::factory()->create(['connection_id' => $bill->connection_id, 'meter_reading_id' => $bill->meter_reading_id, 'period_month' => '2026-08-01', 'number' => 'VOID-1', 'status' => 'void', 'created_at' => '2026-09-05 10:00:00']);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $bill->water_provider_id]);

    $this->actingAs($staff);
    foreach (['daily' => '2026-09-05', 'monthly' => '2026-09', 'yearly' => '2026'] as $group => $period) {
        $this->get(route('provider.reports.billing', ['group' => $group, 'from' => '2026-09-01', 'to' => '2026-09-30']))->assertOk()->assertSee($period)->assertSee('₱150.00');
    }
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

test('reconnection checks the consumer balance across all their connections', function () {
    $bill = Bill::factory()->create();
    $connection = Connection::factory()->create(['consumer_id' => $bill->consumer_id, 'water_provider_id' => $bill->water_provider_id, 'status' => 'disconnected']);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $bill->water_provider_id]);

    $this->actingAs($staff)->post(route('provider.service.store', $connection), ['action' => 'request_reconnection', 'reason' => 'Request for second meter'])->assertSessionHasErrors('action');

    expect($connection->fresh()->status)->toBe('disconnected');
    $this->assertDatabaseCount('service_events', 0);
});
