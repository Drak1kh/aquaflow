<?php

use App\Models\Consumer;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\WaterProvider;
use App\UserRole;

test('super admins can open every administration module', function (string $routeName) {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->get(route($routeName))->assertOk();
})->with([
    'users' => 'admin.users.index',
    'providers' => 'admin.providers.index',
    'permissions' => 'admin.permissions.index',
    'billing' => 'admin.billing.index',
    'payments' => 'admin.payments.index',
    'reports' => 'admin.reports.index',
    'tickets' => 'admin.tickets.index',
    'settings' => 'admin.settings.index',
]);

test('a consumer cannot open administration modules', function () {
    $consumer = User::factory()->create();

    $this->actingAs($consumer)->get(route('admin.users.index'))->assertForbidden();
});

test('an administrator can register a provider and create its staff account', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->post(route('admin.providers.store'), [
        'name' => 'North Water', 'code' => 'north-water', 'email' => 'north@example.com',
    ])->assertRedirect();
    $provider = WaterProvider::where('code', 'north-water')->firstOrFail();

    $this->post(route('admin.users.store'), [
        'name' => 'Provider Clerk', 'email' => 'clerk@example.com', 'role' => 'water_provider',
        'water_provider_id' => $provider->id, 'password' => 'temporary-long-password',
        'password_confirmation' => 'temporary-long-password',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $staff = User::where('email', 'clerk@example.com')->firstOrFail();
    expect($staff->role)->toBe(UserRole::WaterProvider);
    expect($staff->water_provider_id)->toBe($provider->id);
    expect($staff->must_change_password)->toBeTrue();
});

test('the last active super admin cannot be deactivated', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->patch(route('admin.users.status', $admin))->assertSessionHasErrors('role');

    expect($admin->fresh()->is_active)->toBeTrue();
});

test('role permissions can revoke a provider action', function () {
    $admin = User::factory()->superAdmin()->create();
    $provider = WaterProvider::create(['name' => 'North Water', 'code' => 'north-water']);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $provider->id]);

    $this->actingAs($admin)->put(route('admin.permissions.update'), [
        'role' => 'water_provider', 'permissions' => ['consumers.view'],
    ])->assertSessionHasNoErrors();

    $this->actingAs($staff)->post(route('provider.consumers.store'), [
        'account_number' => 'A-001', 'name' => 'Client', 'address' => 'North Street',
    ])->assertForbidden();
});

test('a consumer role cannot be granted provider management actions', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->put(route('admin.permissions.update'), [
        'role' => 'consumer', 'permissions' => ['consumers.manage'],
    ])->assertSessionHasErrors('permissions');

    $this->assertDatabaseCount('role_permissions', 0);
});

test('system settings accept the configured date formats', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->put(route('admin.settings.update'), [
        'platform_name' => 'AquaFlow Operations',
        'due_days' => 14,
        'grace_days' => 7,
        'late_fee' => '25.00',
        'date_format' => 'Y-m-d',
        'email_notifications' => 0,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas('system_settings', ['key' => 'date_format', 'value' => 'Y-m-d']);
    $this->actingAs($admin)->get(route('admin.users.show', $admin))
        ->assertOk()
        ->assertSee('AquaFlow Operations')
        ->assertSee($admin->created_at->format('Y-m-d'));
});

test('an escalated ticket can only be assigned to an active super admin', function () {
    $admin = User::factory()->superAdmin()->create();
    $inactiveAdmin = User::factory()->superAdmin()->create(['is_active' => false]);
    $provider = WaterProvider::create(['name' => 'North Water', 'code' => 'north-water']);
    $consumer = Consumer::create(['water_provider_id' => $provider->id, 'account_number' => 'A-001', 'name' => 'Client', 'address' => 'North Street']);
    $ticket = SupportTicket::create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'type' => 'billing', 'subject' => 'Review bill', 'description' => 'The amount seems incorrect.', 'escalated_at' => now()]);

    $this->actingAs($admin)->put(route('admin.tickets.update', $ticket), [
        'status' => 'under_review', 'assigned_to' => $inactiveAdmin->id,
    ])->assertSessionHasErrors('assigned_to');

    $this->put(route('admin.tickets.update', $ticket), [
        'status' => 'under_review', 'assigned_to' => $admin->id,
    ])->assertSessionHasNoErrors();

    expect($ticket->fresh()->assigned_to)->toBe($admin->id);
});

test('an empty CSV report still includes its column headings', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->get(route('admin.reports.csv', ['type' => 'collections']))
        ->assertOk()
        ->assertStreamedContent("Provider,Reference,Method,\"Paid at\",\"Collected PHP\"\n");
});
