<?php

use App\Models\Consumer;
use App\Models\Permission;
use App\Models\ProviderMembership;
use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\WaterProvider;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('admin dashboard shows live totals and recently added providers', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $provider = WaterProvider::factory()->create(['name' => 'North District Water']);
    $consumer = Consumer::factory()->create(['water_provider_id' => $provider->id]);
    SupportTicket::factory()->create([
        'water_provider_id' => $provider->id,
        'consumer_id' => $consumer->id,
        'status' => 'under_review',
        'escalated_at' => now(),
    ]);

    $this->actingAs($admin)->get(route('admin.index'))
        ->assertOk()
        ->assertViewHas('providerCount', 1)
        ->assertViewHas('activeProviderCount', 1)
        ->assertViewHas('escalatedTicketCount', 1)
        ->assertSee('Admin dashboard')
        ->assertSee($provider->name);
});

test('provider management has its own admin-only page', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $staff = User::factory()->create();
    $provider = WaterProvider::factory()->create(['name' => 'South District Water']);

    $this->actingAs($staff)->get(route('admin.index'))->assertForbidden();
    $this->actingAs($staff)->get(route('admin.providers.index'))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.providers.index'))
        ->assertOk()
        ->assertSee('Provider directory')
        ->assertSee($provider->name);
});

test('super admin can create and invite a user with an account role without a provider', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $role = Role::factory()->create();
    Notification::fake();

    $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Mara Santos',
        'email' => 'MARA@example.test',
        'role_id' => $role->id,
    ])->assertRedirect(route('admin.users.index'));

    $user = User::where('email', 'mara@example.test')->firstOrFail();
    expect($user->role_id)->toBe($role->id);
    $this->assertDatabaseMissing('provider_memberships', ['user_id' => $user->id]);
    Notification::assertSentTo($user, ResetPassword::class);
    Notification::assertSentTo($user, VerifyEmail::class);
    $this->assertDatabaseHas('audit_logs', ['subject_type' => $user->getMorphClass(), 'subject_id' => $user->id, 'action' => 'user.created']);
});

test('super admin can create an account before assigning a role', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    Notification::fake();

    $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Unassigned User',
        'email' => 'unassigned@example.test',
    ])->assertRedirect(route('admin.users.index'));

    $user = User::where('email', 'unassigned@example.test')->firstOrFail();
    expect($user->role_id)->toBeNull();
    Notification::assertSentTo($user, ResetPassword::class);
});

test('super admin can browse users and role permissions', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $role = Role::factory()->create(['name' => 'Meter reader']);
    $user = User::factory()->create(['name' => 'Ana Reyes']);

    $this->actingAs($admin)->get(route('admin.users.index', ['search' => 'Ana']))->assertOk()->assertSee('Ana Reyes');
    $this->get(route('admin.roles.index'))->assertOk()->assertSee($role->name);
});

test('user directory filters, sorts, and paginates accounts', function () {
    $admin = User::factory()->create(['name' => 'Admin Account', 'is_super_admin' => true]);

    foreach (range(1, 11) as $number) {
        User::factory()->create(['name' => sprintf('Member %02d', $number)]);
    }

    $this->actingAs($admin);

    Livewire::test('pages::admin-users')
        ->assertSee('Member 09')
        ->assertDontSee('Member 11')
        ->call('nextPage')
        ->assertSee('Member 11')
        ->call('sort', 'name')
        ->assertSee('Member 11')
        ->set('search', 'Member 03')
        ->assertSee('Member 03')
        ->assertDontSee('Member 11');
});

test('provider directory sorts and paginates organizations', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);

    foreach (range(1, 11) as $number) {
        WaterProvider::factory()->create(['name' => sprintf('Provider %02d', $number)]);
    }

    $this->actingAs($admin);

    Livewire::test('pages::admin-providers')
        ->assertSee('Provider 10')
        ->assertDontSee('Provider 11')
        ->call('nextPage')
        ->assertSee('Provider 11')
        ->call('sort', 'name')
        ->assertSee('Provider 11')
        ->assertDontSee('Provider 01');
});

test('role directory sorts and paginates roles', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);

    foreach (range(1, 11) as $number) {
        Role::factory()->create(['name' => sprintf('Role %02d', $number)]);
    }

    $this->actingAs($admin);

    Livewire::test('pages::admin-roles')
        ->assertSee('Role 10')
        ->assertDontSee('Role 11')
        ->call('nextPage')
        ->assertSee('Role 11')
        ->call('sort', 'name')
        ->assertSee('Role 11')
        ->assertDontSee('Role 01');
});

test('super admin can create a role with selected permissions', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $permission = Permission::factory()->create(['code' => 'readings.manage', 'name' => 'Manage readings']);

    $this->actingAs($admin)->post(route('admin.roles.store'), [
        'name' => 'Meter reader',
        'slug' => 'meter_reader',
        'permissions' => [$permission->id],
    ])->assertRedirect(route('admin.roles.index'));

    $role = Role::where('slug', 'meter_reader')->firstOrFail();
    $this->assertDatabaseHas('permission_role', ['role_id' => $role->id, 'permission_id' => $permission->id]);
});

test('duplicate email cannot create another account or send an invitation', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $existing = User::factory()->create(['email' => 'mara@example.test']);
    $role = Role::factory()->create();
    Notification::fake();

    $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Another Mara',
        'email' => 'mara@example.test',
        'role_id' => $role->id,
    ])->assertSessionHasErrors('email');

    $this->assertDatabaseCount('users', 2);
    expect($existing->fresh()->role_id)->toBeNull();
    Notification::assertNothingSent();
});

test('an invalid account role does not create a user', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    Notification::fake();

    $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Invalid Role',
        'email' => 'invalid-role@example.test',
        'role_id' => 999999,
    ])->assertSessionHasErrors('role_id');

    $this->assertDatabaseMissing('users', ['email' => 'invalid-role@example.test']);
    Notification::assertNothingSent();
});

test('only an active super admin can view or change user access', function () {
    $staff = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($staff)->get(route('admin.users.index'))->assertForbidden();
    $this->post(route('admin.users.store'), ['name' => 'Blocked', 'email' => 'blocked@example.test'])->assertForbidden();
    $this->get(route('admin.roles.index'))->assertForbidden();
    $this->put(route('admin.users.update', $target), ['status' => 'inactive'])->assertForbidden();

    expect($target->fresh()->status)->toBe('active');
});

test('super admin can update account details and deactivate another user', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $user = User::factory()->create();
    $role = Role::factory()->create();
    Notification::fake();

    $this->actingAs($admin)->put(route('admin.users.update', $user), [
        'name' => 'New Name',
        'email' => 'new@example.test',
        'status' => 'inactive',
        'role_id' => $role->id,
    ])->assertRedirect();

    $user->refresh();
    expect($user->name)->toBe('New Name');
    expect($user->email)->toBe('new@example.test');
    expect($user->status)->toBe('inactive');
    expect($user->role_id)->toBe($role->id);
    expect($user->email_verified_at)->toBeNull();
    Notification::assertSentTo($user, VerifyEmail::class);
    $this->actingAs($user)->get(route('dashboard'))->assertForbidden();

    $this->actingAs($admin)->put(route('admin.users.update', $user), ['status' => 'active'])->assertRedirect();
    expect($user->fresh()->status)->toBe('active');
});

test('super admin can remove a user account role', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $role = Role::factory()->create();
    $user = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($admin)->put(route('admin.users.update', $user), [
        'status' => 'active',
        'role_id' => '',
    ])->assertRedirect();

    expect($user->fresh()->role_id)->toBeNull();
});

test('invalid role changes do not update an account', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $user = User::factory()->create();

    $this->actingAs($admin)->put(route('admin.users.update', $user), [
        'name' => 'Should Not Save',
        'status' => 'inactive',
        'role_id' => 999999,
    ])->assertSessionHasErrors('role_id');

    expect($user->fresh()->name)->not->toBe('Should Not Save');
    expect($user->fresh()->status)->toBe('active');
});

test('an account role alone does not grant access to provider data', function () {
    $this->seed(AuthorizationSeeder::class);
    $role = Role::where('slug', 'provider_manager')->firstOrFail();
    $user = User::factory()->create(['role_id' => $role->id]);
    $provider = WaterProvider::factory()->create();

    expect($user->canInProvider('consumers.view', $provider->id))->toBeFalse();
});

test('super admin cannot deactivate their own account', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);

    $this->actingAs($admin)->put(route('admin.users.update', $admin), ['status' => 'inactive'])->assertUnprocessable();

    expect($admin->fresh()->status)->toBe('active');
});

test('assigning a provider role controls access to that providers modules', function () {
    $this->seed(AuthorizationSeeder::class);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $staff = User::factory()->create();
    $provider = WaterProvider::factory()->create();
    $role = Role::factory()->create();
    $role->permissions()->attach(Permission::where('code', 'consumers.view')->firstOrFail());

    $this->actingAs($admin)->post(route('admin.users.memberships.store', $staff), [
        'water_provider_id' => $provider->id,
        'role_id' => $role->id,
        'status' => 'active',
    ])->assertRedirect();

    $this->assertDatabaseHas('provider_memberships', ['user_id' => $staff->id, 'water_provider_id' => $provider->id, 'role_id' => $role->id]);
    $this->actingAs($staff)->withSession(['active_provider_id' => $provider->id])->get(route('provider.index'))->assertOk();

    $this->actingAs($admin)->post(route('admin.users.memberships.store', $staff), [
        'water_provider_id' => $provider->id,
        'role_id' => $role->id,
        'status' => 'inactive',
    ])->assertRedirect();

    $this->actingAs($staff)->withSession(['active_provider_id' => $provider->id])->get(route('provider.index'))->assertForbidden();
});

test('editing a role changes the permissions of its assigned users', function () {
    $this->seed(AuthorizationSeeder::class);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $staff = User::factory()->create();
    $provider = WaterProvider::factory()->create();
    $role = Role::factory()->create();
    $permission = Permission::where('code', 'consumers.view')->firstOrFail();
    ProviderMembership::factory()->create(['water_provider_id' => $provider->id, 'user_id' => $staff->id, 'role_id' => $role->id]);

    $this->actingAs($admin)->put(route('admin.roles.update', $role), [
        'name' => 'Viewer',
        'permissions' => [$permission->id],
    ])->assertRedirect();

    expect($role->fresh()->name)->toBe('Viewer');
    $this->assertDatabaseHas('permission_role', ['role_id' => $role->id, 'permission_id' => $permission->id]);
    $this->actingAs($staff)->withSession(['active_provider_id' => $provider->id])->get(route('provider.index'))->assertOk();

    $this->actingAs($admin)->put(route('admin.roles.update', $role), ['name' => 'Viewer'])->assertRedirect();

    $this->actingAs($staff)->withSession(['active_provider_id' => $provider->id])->get(route('provider.index'))->assertForbidden();
});

test('invalid role permissions do not change access', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $role = Role::factory()->create();

    $this->actingAs($admin)->put(route('admin.roles.update', $role), ['name' => 'Changed', 'permissions' => [999999]])->assertSessionHasErrors('permissions.0');

    expect($role->fresh()->name)->not->toBe('Changed');
});
