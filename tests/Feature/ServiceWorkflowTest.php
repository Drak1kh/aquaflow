<?php

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Payment;
use App\Models\ProviderMembership;
use App\Models\Role;
use App\Models\ServiceAction;
use App\Models\User;
use App\Models\WaterConnection;
use App\Models\WaterMeter;
use App\Models\WaterProvider;
use Database\Seeders\AuthorizationSeeder;

test('reconnection needs payment, approval, and recorded restoration', function () {
    $this->seed(AuthorizationSeeder::class);
    $provider = WaterProvider::factory()->create(['reconnection_fee' => '10.00']);
    $staff = User::factory()->create();
    ProviderMembership::factory()->create(['water_provider_id' => $provider->id, 'user_id' => $staff->id, 'role_id' => Role::where('slug', 'provider_manager')->firstOrFail()->id]);
    $consumer = Consumer::factory()->create(['water_provider_id' => $provider->id]);
    $meter = WaterMeter::factory()->create(['water_provider_id' => $provider->id]);
    $connection = WaterConnection::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'water_meter_id' => $meter->id]);
    $bill = Bill::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'water_connection_id' => $connection->id, 'due_on' => now()->subDays(40)->toDateString()]);

    $this->actingAs($staff)->withSession(['active_provider_id' => $provider->id]);
    $route = route('provider.service.act', $connection);
    $this->post($route, ['action' => 'disconnect'])->assertSessionHasErrors('action');
    $this->post($route, ['action' => 'notice'])->assertSessionHasNoErrors();
    $this->post($route, ['action' => 'disconnect'])->assertSessionHasNoErrors();
    expect($connection->fresh()->status)->toBe('disconnected');
    $this->post($route, ['action' => 'reconnection_request'])->assertSessionHasErrors('action');

    $this->post(route('provider.payments.store'), ['bill_id' => $bill->id, 'amount' => '75.00', 'method' => 'cash'])->assertSessionHasNoErrors();
    $payment = Payment::where('intended_bill_id', $bill->id)->firstOrFail();
    $this->post(route('provider.payments.verify', $payment))->assertSessionHasNoErrors();
    expect($connection->fresh()->status)->toBe('disconnected');
    $this->post($route, ['action' => 'reconnection_request'])->assertSessionHasNoErrors();
    $this->post($route, ['action' => 'restore'])->assertSessionHasErrors('action');
    $this->post($route, ['action' => 'approve'])->assertSessionHasNoErrors();
    expect($bill->fresh()->balanceCents())->toBe(1000);
    $this->post($route, ['action' => 'restore'])->assertSessionHasErrors('action');
    $this->post(route('provider.payments.store'), ['bill_id' => $bill->id, 'amount' => '10.00', 'method' => 'cash'])->assertSessionHasNoErrors();
    $feePayment = Payment::where('intended_bill_id', $bill->id)->latest('id')->firstOrFail();
    $this->post(route('provider.payments.verify', $feePayment))->assertSessionHasNoErrors();
    $this->post($route, ['action' => 'restore'])->assertSessionHasNoErrors();
    expect($connection->fresh()->status)->toBe('active')->and(ServiceAction::where('water_connection_id', $connection->id)->count())->toBe(5);
});
