<?php

use App\Models\Bill;
use App\Models\BillingPeriod;
use App\Models\Connection;
use App\Models\Consumer;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\User;
use App\Models\WaterRate;

test('monthly bills use the configured period due date and preserve the snapshot', function () {
    $connection = Connection::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $connection->water_provider_id]);
    MeterReading::factory()->create(['connection_id' => $connection->id, 'water_provider_id' => $connection->water_provider_id, 'period_month' => '2026-10-01', 'previous_reading' => '10.000', 'current_reading' => '15.000']);
    WaterRate::factory()->create(['water_provider_id' => $connection->water_provider_id, 'effective_from' => '2026-01-01', 'base_charge' => '50.00', 'unit_rate' => '10.00']);

    $this->actingAs($staff)->post(route('provider.periods.store'), ['period_month' => '2026-10', 'due_at' => '2026-11-20'])->assertSessionHasNoErrors();
    $this->post(route('provider.bills.store'), ['connection_id' => $connection->id, 'period_month' => '2026-10'])->assertSessionHasNoErrors();

    $bill = Bill::firstOrFail();
    expect($bill->due_at->toDateString())->toBe('2026-11-20');
    expect($bill->total)->toBe('100.00');
    BillingPeriod::firstOrFail()->update(['due_at' => '2026-11-25']);
    expect($bill->fresh()->due_at->toDateString())->toBe('2026-11-20');
    $this->post(route('provider.periods.store'), ['period_month' => '2026-10', 'due_at' => '2026-11-30'])->assertSessionHasErrors('period_month');
    expect(BillingPeriod::firstOrFail()->due_at->toDateString())->toBe('2026-11-25');
});

test('reports apply consumer and date filters to bills collections and readings', function () {
    $bill = Bill::factory()->create(['period_month' => '2026-10-01']);
    $bill->consumer->update(['name' => 'Ada Rivera']);
    MeterReading::whereKey($bill->meter_reading_id)->update(['period_month' => '2026-10-01', 'previous_reading' => '5.000', 'current_reading' => '12.000']);
    Payment::factory()->create(['bill_id' => $bill->id, 'consumer_id' => $bill->consumer_id, 'water_provider_id' => $bill->water_provider_id, 'status' => 'verified', 'amount' => '40.00', 'paid_at' => '2026-10-15']);
    $otherConsumer = Consumer::factory()->create(['water_provider_id' => $bill->water_provider_id, 'name' => 'Other Consumer']);
    $otherConnection = Connection::factory()->create(['water_provider_id' => $bill->water_provider_id, 'consumer_id' => $otherConsumer->id]);
    $otherBill = Bill::factory()->create(['connection_id' => $otherConnection->id, 'period_month' => '2026-09-01']);
    Payment::factory()->create(['bill_id' => $otherBill->id, 'consumer_id' => $otherConsumer->id, 'water_provider_id' => $bill->water_provider_id, 'amount' => '90.00', 'status' => 'verified', 'paid_at' => '2026-10-15']);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $bill->water_provider_id]);

    $this->actingAs($staff)->get(route('management', ['section' => 'reports', 'search' => 'Ada', 'from' => '2026-10-01', 'to' => '2026-10-31']))
        ->assertOk()->assertSee('Verified collections: ₱40.00')->assertSee('Consumption: 7.000')->assertSee('Bills generated: 1')->assertDontSee('Other Consumer');
});

test('the consumer dashboard distinguishes current bills latest usage and oldest unpaid due date', function () {
    $this->travelTo(now()->setDate(2026, 11, 3));
    $bill = Bill::factory()->create(['period_month' => '2026-10-01', 'due_at' => '2026-10-25']);
    $consumer = User::factory()->create(['consumer_id' => $bill->consumer_id]);
    MeterReading::whereKey($bill->meter_reading_id)->update(['period_month' => '2026-10-01', 'previous_reading' => '3.000', 'current_reading' => '10.000']);

    $this->actingAs($consumer)->get(route('dashboard.consumer'))->assertOk()
        ->assertSee('No bill generated for this month.')->assertSee('7.000 m³')->assertSee('Oct 25, 2026')->assertSee('Overdue');
});

test('the first reading accepts an opening value and later readings use saved history', function () {
    $connection = Connection::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $connection->water_provider_id]);

    $this->actingAs($staff)->post(route('provider.readings.store'), ['connection_id' => $connection->id, 'period_month' => '2026-09', 'previous_reading' => '100.000', 'current_reading' => '110.000'])->assertSessionHasNoErrors();
    $this->post(route('provider.readings.store'), ['connection_id' => $connection->id, 'period_month' => '2026-10', 'previous_reading' => '0', 'current_reading' => '115.000'])->assertSessionHasNoErrors();

    $readings = MeterReading::orderBy('period_month')->get();
    expect($readings[0]->previous_reading)->toBe('100.000');
    expect($readings[1]->previous_reading)->toBe('110.000');
    $this->post(route('provider.readings.store'), ['connection_id' => $connection->id, 'period_month' => '2026-11', 'current_reading' => '114.000'])->assertSessionHasErrors('current_reading');
    $this->assertDatabaseCount('meter_readings', 2);
});

test('rejected payment proof leaves the balance unchanged and cannot be verified', function () {
    $bill = Bill::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $bill->water_provider_id]);
    $payment = Payment::factory()->create(['bill_id' => $bill->id, 'consumer_id' => $bill->consumer_id, 'water_provider_id' => $bill->water_provider_id, 'amount' => '40.00', 'status' => 'pending']);

    $this->actingAs($staff)->post(route('provider.payments.reject', $payment), ['remarks' => 'Reference does not match.'])->assertSessionHasNoErrors();

    expect($payment->fresh()->status)->toBe('rejected');
    expect($bill->fresh()->balance())->toBe('150.00');
    $this->post(route('provider.payments.verify', $payment))->assertSessionHasErrors('payment');
    $this->assertDatabaseCount('payment_allocations', 0);
});
