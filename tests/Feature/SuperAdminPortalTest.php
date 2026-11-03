<?php

use App\Actions\PaymentService;
use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Payment;
use App\Models\User;
use App\Models\WaterProvider;
use App\UserRole;

test('the super admin dashboard spans providers without creating or selecting a provider', function () {
    $first = WaterProvider::factory()->create();
    $second = WaterProvider::factory()->create();
    Consumer::factory()->create(['water_provider_id' => $first->id]);
    Consumer::factory()->create(['water_provider_id' => $second->id]);
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->get(route('dashboard'))->assertRedirect(route('dashboard.admin'));
    $this->get(route('dashboard.admin'))->assertOk()->assertSee('Platform dashboard')->assertSee('Roles &amp; Permissions', false)->assertSee('Recent system activity');
    $this->assertDatabaseCount('water_providers', 2);
    expect($admin->fresh()->water_provider_id)->toBeNull();
    foreach (['admin.users.index', 'admin.providers.index', 'admin.permissions.index', 'admin.billing.index', 'admin.payments.index', 'admin.reports.index', 'admin.tickets.index', 'admin.settings.index'] as $route) {
        $this->get(route($route))->assertOk();
    }
});

test('provider data remains scoped when multiple providers exist and arbitrary context input is ignored', function () {
    $first = WaterProvider::factory()->create();
    $second = WaterProvider::factory()->create();
    $owned = Consumer::factory()->create(['water_provider_id' => $first->id, 'name' => 'Owned Consumer']);
    $other = Consumer::factory()->create(['water_provider_id' => $second->id, 'name' => 'Private Other Consumer']);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $first->id]);

    $this->actingAs($staff)->get(route('provider.consumers.index', ['provider_context' => $second->id]))->assertOk()->assertSee('Owned Consumer')->assertDontSee('Private Other Consumer');
    $this->put(route('provider.consumers.update', $other), ['name' => 'Tampered', 'address' => 'Changed'])->assertNotFound();
    expect($other->fresh()->name)->toBe('Private Other Consumer');
    $this->get(route('admin.billing.index'))->assertForbidden();
});

test('revoking a role permission blocks both the module page and its write action', function () {
    $provider = WaterProvider::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $provider->id]);
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->put(route('admin.permissions.update'), ['role' => UserRole::WaterProvider->value, 'permissions' => []])->assertSessionHasNoErrors();
    $this->actingAs($staff)->get(route('provider.consumers.index'))->assertForbidden();
    $this->post(route('provider.consumers.store'), ['name' => 'Denied', 'address' => 'Denied address'])->assertForbidden();
    $this->get(route('provider.reports.billing'))->assertForbidden();
    $this->get(route('provider.profile.edit'))->assertForbidden();
    $this->assertDatabaseCount('consumers', 0);
});

test('oversight filters collections and outstanding balances across providers correctly', function () {
    $bill = Bill::factory()->create(['total' => '150.00']);
    $otherBill = Bill::factory()->create(['total' => '300.00']);
    $admin = User::factory()->superAdmin()->create();
    $payment = Payment::factory()->create(['bill_id' => $bill->id, 'consumer_id' => $bill->consumer_id, 'water_provider_id' => $bill->water_provider_id, 'amount' => '40.00', 'status' => 'pending']);
    app(PaymentService::class)->verify($payment, $admin);

    $this->actingAs($admin)->get(route('dashboard.admin'))->assertOk()->assertViewHas('outstanding', '410.00');
    $this->get(route('admin.payments.index', ['provider' => $bill->water_provider_id]))->assertOk()->assertViewHas('collected', '40');
    $this->get(route('admin.billing.index', ['provider' => $otherBill->water_provider_id]))->assertOk()->assertSee($otherBill->number)->assertDontSee($bill->number);
    $this->get(route('admin.reports.csv', ['type' => 'performance']))->assertOk();
});

test('a super admin explicitly selects provider operations without changing the account association', function () {
    $first = WaterProvider::factory()->create();
    $second = WaterProvider::factory()->create(['name' => 'Selected Provider']);
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->get(route('dashboard.provider', ['provider_context' => $second->id]))->assertOk()->assertSee('Selected Provider');
    $this->get(route('provider.consumers.index'))->assertOk()->assertViewHas('provider', fn ($provider) => $provider->id === $second->id);
    $this->get(route('dashboard.admin'))->assertOk();
    expect($admin->fresh()->water_provider_id)->toBeNull();
});

test('billing oversight derives overdue status from the due date and remaining balance', function () {
    $bill = Bill::factory()->create(['due_at' => today()->subDay(), 'status' => 'unpaid']);
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->get(route('admin.billing.index', ['status' => 'overdue']))->assertOk()->assertSee($bill->number)->assertSee('Overdue');
    $this->get(route('admin.billing.index', ['status' => 'unpaid']))->assertOk()->assertDontSee($bill->number);
});
