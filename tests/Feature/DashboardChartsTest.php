<?php

use App\Models\Bill;
use App\Models\Connection;
use App\Models\Payment;
use App\Models\RolePermission;
use App\Models\User;
use App\UserRole;

test('the platform chart aggregates monthly billing and verified payments across providers', function () {
    $this->travelTo(now()->setDate(2026, 1, 15));
    $admin = User::factory()->superAdmin()->create();
    $bill = Bill::factory()->create(['total' => '100.25']);
    Bill::factory()->create(['total' => '50.00']);
    Bill::factory()->create(['total' => '999.00', 'status' => 'void']);
    Bill::factory()->create(['total' => '800.00', 'period_month' => '2025-07-01']);
    Bill::factory()->create(['total' => '700.00', 'period_month' => '2026-02-01']);
    Payment::factory()->create(['bill_id' => $bill->id, 'amount' => '20.10', 'status' => 'verified', 'paid_at' => '2026-01-03']);
    Payment::factory()->create(['bill_id' => $bill->id, 'amount' => '30.15', 'status' => 'verified', 'paid_at' => '2026-01-14']);
    Payment::factory()->create(['bill_id' => $bill->id, 'amount' => '900.00', 'status' => 'pending']);

    $response = $this->actingAs($admin)->get(route('dashboard.admin'));

    $response->assertOk()->assertSee('Billing and collections');
    $trend = $response->viewData('financialTrend');
    expect($trend)->toHaveCount(6);
    expect($trend[0])->toBe(['month' => 'Aug 2025', 'billed' => '0.00', 'collected' => '0.00']);
    expect($trend[5])->toBe(['month' => 'Jan 2026', 'billed' => '150.25', 'collected' => '50.25']);
});

test('provider charts contain only the providers billing payments and consumption', function () {
    $this->travelTo(now()->setDate(2026, 1, 15));
    $bill = Bill::factory()->create(['total' => '125.00']);
    $otherBill = Bill::factory()->create(['total' => '900.00']);
    Payment::factory()->create(['bill_id' => $bill->id, 'amount' => '25.00', 'status' => 'verified']);
    Payment::factory()->create(['bill_id' => $otherBill->id, 'amount' => '800.00', 'status' => 'verified']);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $bill->water_provider_id]);

    $response = $this->actingAs($staff)->get(route('dashboard.provider'));

    $response->assertOk()->assertSee('Water consumption');
    expect($response->viewData('financialTrend')[5])->toBe(['month' => 'Jan 2026', 'billed' => '125.00', 'collected' => '25.00']);
    expect($response->viewData('consumptionTrend')[5])->toBe(['month' => 'Jan 2026', 'consumption' => '10.000']);
});

test('consumer charts combine their connections and exclude other consumers in the same provider', function () {
    $this->travelTo(now()->setDate(2026, 1, 15));
    $bill = Bill::factory()->create();
    $secondConnection = Connection::factory()->create(['consumer_id' => $bill->consumer_id, 'water_provider_id' => $bill->water_provider_id]);
    Bill::factory()->create(['connection_id' => $secondConnection->id, 'total' => '75.00']);
    $otherConnection = Connection::factory()->create(['water_provider_id' => $bill->water_provider_id]);
    Bill::factory()->create(['connection_id' => $otherConnection->id, 'total' => '999.00']);
    $client = User::factory()->create(['consumer_id' => $bill->consumer_id]);

    $response = $this->actingAs($client)->get(route('dashboard.consumer'));

    $response->assertOk()->assertSee('Monthly water bills');
    expect($response->viewData('financialTrend')[5]['billed'])->toBe('225.00');
    expect($response->viewData('consumptionTrend')[5]['consumption'])->toBe('20.000');
});

test('financial charts are omitted when billing permission is revoked', function (UserRole $role, string $route) {
    $this->travelTo(now()->setDate(2026, 1, 15));
    $bill = Bill::factory()->create();
    $user = User::factory()->create(['role' => $role, 'consumer_id' => $role === UserRole::Consumer ? $bill->consumer_id : null, 'water_provider_id' => $bill->water_provider_id]);
    RolePermission::factory()->create(['role' => $role->value, 'permission' => 'billing.view', 'allowed' => false]);

    $response = $this->actingAs($user)->get(route($route));

    $response->assertOk()->assertDontSee('Monthly water bills')->assertDontSee('Billing and collections');
    expect($response->viewData('financialTrend'))->toBe([]);
    expect($response->viewData('consumptionTrend')[5]['consumption'])->toBe('10.000');
})->with([
    [UserRole::Consumer, 'dashboard.consumer'],
    [UserRole::WaterProvider, 'dashboard.provider'],
]);

test('provider charts omit collections without payment permission', function () {
    $this->travelTo(now()->setDate(2026, 1, 15));
    $bill = Bill::factory()->create();
    Payment::factory()->create(['bill_id' => $bill->id, 'amount' => '50.00', 'status' => 'verified']);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $bill->water_provider_id]);
    RolePermission::factory()->create(['role' => UserRole::WaterProvider->value, 'permission' => 'payments.view', 'allowed' => false]);

    $response = $this->actingAs($staff)->get(route('dashboard.provider'));

    $response->assertOk();
    expect($response->viewData('financialTrend')[5]['collected'])->toBe('0.00');
    $response->assertDontSee('Collected (PHP)');
});
