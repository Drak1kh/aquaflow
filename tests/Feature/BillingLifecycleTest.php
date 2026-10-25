<?php

use App\Models\Bill;
use App\Models\BillingPeriod;
use App\Models\Consumer;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\ProviderMembership;
use App\Models\Role;
use App\Models\User;
use App\Models\WaterConnection;
use App\Models\WaterMeter;
use App\Models\WaterProvider;
use App\Models\WaterRate;
use Database\Seeders\AuthorizationSeeder;

test('a bill snapshots readings and rates and partial payment reduces its balance', function () {
    $this->seed(AuthorizationSeeder::class);
    $provider = WaterProvider::factory()->create();
    $staff = User::factory()->create();
    ProviderMembership::factory()->create(['water_provider_id' => $provider->id, 'user_id' => $staff->id, 'role_id' => Role::where('slug', 'provider_manager')->firstOrFail()->id]);
    $consumer = Consumer::factory()->create(['water_provider_id' => $provider->id]);
    $meter = WaterMeter::factory()->create(['water_provider_id' => $provider->id]);
    $connection = WaterConnection::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'water_meter_id' => $meter->id]);
    $period = BillingPeriod::factory()->create(['water_provider_id' => $provider->id]);
    $reading = MeterReading::factory()->create(['water_provider_id' => $provider->id, 'water_connection_id' => $connection->id, 'water_meter_id' => $meter->id, 'entered_by' => $staff->id, 'reading_date' => now()->toDateString()]);
    $rate = WaterRate::factory()->create(['water_provider_id' => $provider->id]);

    $this->actingAs($staff)->withSession(['active_provider_id' => $provider->id]);
    $this->post(route('provider.bills.generate'), ['billing_period_id' => $period->id, 'meter_reading_id' => $reading->id])->assertSessionHasNoErrors();
    $bill = Bill::where('water_connection_id', $connection->id)->firstOrFail();
    expect($bill->total_amount)->toBe('75.00')->and($bill->balanceCents())->toBe(7500);

    $rate->update(['unit_rate' => '9.0000']);
    expect($bill->fresh()->unit_rate)->toBe('2.5000');
    $this->post(route('provider.bills.generate'), ['billing_period_id' => $period->id, 'meter_reading_id' => $reading->id])->assertSessionHasErrors('reading');

    $this->post(route('provider.payments.store'), ['bill_id' => $bill->id, 'amount' => '25.00', 'method' => 'cash'])->assertSessionHasNoErrors();
    $payment = Payment::where('intended_bill_id', $bill->id)->firstOrFail();
    $this->post(route('provider.payments.verify', $payment))->assertSessionHasNoErrors();
    expect($bill->fresh()->balanceCents())->toBe(5000)->and($bill->fresh()->status)->toBe('partially_paid');
    $this->post(route('provider.payments.verify', $payment))->assertSessionHasErrors('payment');
    $this->get(route('provider.payments.receipt', $payment))->assertOk();
    $this->post(route('provider.payments.reverse', $payment), ['remarks' => 'Bank returned payment'])->assertSessionHasNoErrors();
    expect($bill->fresh()->balanceCents())->toBe(7500)->and($bill->fresh()->status)->toBe('unpaid');
    $this->get(route('provider.payments.receipt', $payment))->assertNotFound();
});

test('preview does not persist a bill and batch generation skips existing bills', function () {
    $this->seed(AuthorizationSeeder::class);
    $provider = WaterProvider::factory()->create();
    $staff = User::factory()->create();
    ProviderMembership::factory()->create(['water_provider_id' => $provider->id, 'user_id' => $staff->id, 'role_id' => Role::where('slug', 'provider_manager')->firstOrFail()->id]);
    $consumer = Consumer::factory()->create(['water_provider_id' => $provider->id]);
    $meter = WaterMeter::factory()->create(['water_provider_id' => $provider->id]);
    $connection = WaterConnection::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'water_meter_id' => $meter->id]);
    $period = BillingPeriod::factory()->create(['water_provider_id' => $provider->id]);
    $reading = MeterReading::factory()->create(['water_provider_id' => $provider->id, 'water_connection_id' => $connection->id, 'water_meter_id' => $meter->id, 'entered_by' => $staff->id, 'reading_date' => now()->toDateString()]);
    WaterRate::factory()->create(['water_provider_id' => $provider->id]);

    $this->actingAs($staff)->withSession(['active_provider_id' => $provider->id]);
    $this->get(route('provider.bills.preview', ['billing_period_id' => $period->id, 'meter_reading_id' => $reading->id]))
        ->assertOk()->assertSee('75.00');
    expect(Bill::count())->toBe(0);

    $this->post(route('provider.bills.batch'), ['billing_period_id' => $period->id])->assertSessionHasNoErrors();
    $this->post(route('provider.bills.batch'), ['billing_period_id' => $period->id])->assertSessionHasNoErrors();
    expect(Bill::where('water_connection_id', $connection->id)->count())->toBe(1);

    $bill = Bill::where('water_connection_id', $connection->id)->firstOrFail();
    $this->post(route('provider.bills.void', $bill), ['reason' => 'Incorrect source reading'])->assertRedirect(route('provider.billing'));
    expect($bill->fresh()->status)->toBe('void')->and($bill->fresh()->balanceCents())->toBe(0);
});
