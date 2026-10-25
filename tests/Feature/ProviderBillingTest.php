<?php

use App\Actions\BillingService;
use App\Models\Bill;
use App\Models\Connection;
use App\Models\Consumer;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\User;
use App\Models\WaterProvider;
use App\Models\WaterRate;

function providerBillingRecords(): array
{
    $provider = WaterProvider::create(['name' => 'North Water', 'code' => 'north', 'late_fee' => '15.00']);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $provider->id]);
    $consumer = Consumer::create(['water_provider_id' => $provider->id, 'account_number' => 'A-001', 'name' => 'Ada Rivera', 'address' => 'North Street']);
    $connection = Connection::create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'number' => 'C-001', 'meter_number' => 'M-001']);
    $rate = WaterRate::create(['water_provider_id' => $provider->id, 'base_charge' => '50.00', 'unit_rate' => '12.34', 'effective_from' => '2026-01-01']);
    MeterReading::create(['water_provider_id' => $provider->id, 'connection_id' => $connection->id, 'period_month' => '2026-01-01', 'previous_reading' => '2.000', 'current_reading' => '10.000']);

    return compact('provider', 'staff', 'consumer', 'connection', 'rate');
}

test('a provider generates a monthly bill from the saved reading and rate snapshot', function () {
    ['staff' => $staff, 'connection' => $connection, 'rate' => $rate] = providerBillingRecords();

    $this->actingAs($staff)->post(route('provider.bills.store'), [
        'connection_id' => $connection->id, 'period_month' => '2026-01',
    ])->assertSessionHasNoErrors();

    $bill = Bill::where('connection_id', $connection->id)->firstOrFail();
    expect($bill->total)->toBe('148.72');
    expect($bill->due_at->toDateString())->toBe('2026-02-14');
    $rate->update(['unit_rate' => '50.00']);
    expect($bill->fresh()->unit_rate)->toBe('12.34');

    $this->post(route('provider.bills.store'), ['connection_id' => $connection->id, 'period_month' => '2026-01'])
        ->assertSessionHasErrors('period');
    $this->assertDatabaseCount('bills', 1);
});

test('only verified partial payments reduce the balance', function () {
    ['staff' => $staff, 'connection' => $connection] = providerBillingRecords();
    $bill = app(BillingService::class)->generate($connection, '2026-01-01', $staff);

    $this->actingAs($staff)->post(route('provider.payments.store'), [
        'bill_id' => $bill->id, 'reference' => 'P-001', 'amount' => '40.00', 'method' => 'cash', 'paid_at' => '2026-02-01',
    ])->assertSessionHasNoErrors();
    $payment = Payment::where('reference', 'P-001')->firstOrFail();
    expect($bill->fresh()->balance())->toBe('148.72');

    $this->post(route('provider.payments.verify', $payment))->assertSessionHasNoErrors();
    expect($bill->fresh()->balance())->toBe('108.72');
    expect($bill->fresh()->status)->toBe('partially_paid');

    $this->post(route('provider.payments.verify', $payment))->assertSessionHasErrors('payment');
    expect($bill->fresh()->balance())->toBe('108.72');
});

test('a provider cannot record a payment against another provider bill', function () {
    ['staff' => $staff] = providerBillingRecords();
    $otherProvider = WaterProvider::create(['name' => 'South Water', 'code' => 'south']);
    $otherConsumer = Consumer::create(['water_provider_id' => $otherProvider->id, 'account_number' => 'S-001', 'name' => 'South Client', 'address' => 'South Street']);
    $otherConnection = Connection::create(['water_provider_id' => $otherProvider->id, 'consumer_id' => $otherConsumer->id, 'number' => 'S-C-001', 'meter_number' => 'S-M-001']);
    WaterRate::create(['water_provider_id' => $otherProvider->id, 'base_charge' => '10.00', 'unit_rate' => '1.00', 'effective_from' => '2026-01-01']);
    MeterReading::create(['water_provider_id' => $otherProvider->id, 'connection_id' => $otherConnection->id, 'period_month' => '2026-01-01', 'previous_reading' => '0.000', 'current_reading' => '1.000']);
    $otherStaff = User::factory()->waterProvider()->create(['water_provider_id' => $otherProvider->id]);
    $bill = app(BillingService::class)->generate($otherConnection, '2026-01-01', $otherStaff);

    $this->actingAs($staff)->post(route('provider.payments.store'), [
        'bill_id' => $bill->id, 'reference' => 'BAD-001', 'amount' => '1.00', 'method' => 'cash', 'paid_at' => '2026-02-01',
    ])->assertSessionHasErrors('bill_id');
    $this->assertDatabaseCount('payments', 0);
});

test('a payment for the full bill balance marks the bill paid', function () {
    ['staff' => $staff, 'connection' => $connection] = providerBillingRecords();
    $bill = app(BillingService::class)->generate($connection, '2026-01-01', $staff);

    $this->actingAs($staff)->post(route('provider.payments.store'), [
        'bill_id' => $bill->id, 'reference' => 'P-FULL', 'amount' => $bill->total,
        'method' => 'cash', 'paid_at' => '2026-02-01',
    ])->assertSessionHasNoErrors();

    $payment = Payment::where('reference', 'P-FULL')->firstOrFail();
    $this->post(route('provider.payments.verify', $payment))->assertSessionHasNoErrors();

    expect($bill->fresh()->balance())->toBe('0.00');
    expect($bill->fresh()->status)->toBe('paid');
});

test('a late fee is applied only once after the grace period', function () {
    ['staff' => $staff, 'connection' => $connection] = providerBillingRecords();
    $bill = app(BillingService::class)->generate($connection, '2026-01-01', $staff);
    $this->travelTo(now()->setDate(2026, 3, 1));

    app(BillingService::class)->applyLateFee($bill);
    app(BillingService::class)->applyLateFee($bill);

    expect($bill->fresh()->total)->toBe('163.72');
    expect($bill->fresh()->status)->toBe('overdue');
});
