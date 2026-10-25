<?php

use App\Models\User;
use App\UserRole;
use Illuminate\Support\Facades\Gate;

test('each role reaches its own dashboard', function (UserRole $role, string $routeName) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route($routeName));
    $this->get(route($routeName))->assertOk()->assertSee($role->label());
})->with([
    'super admin' => [UserRole::SuperAdmin, 'dashboard.admin'],
    'water provider' => [UserRole::WaterProvider, 'dashboard.provider'],
    'consumer' => [UserRole::Consumer, 'dashboard.consumer'],
]);

test('each dashboard gate only allows its own role', function (UserRole $role, array $access) {
    $user = User::factory()->make(['role' => $role]);

    expect(Gate::forUser($user)->allows('access-super-admin'))->toBe($access[0]);
    expect(Gate::forUser($user)->allows('access-water-provider'))->toBe($access[1]);
    expect(Gate::forUser($user)->allows('access-consumer'))->toBe($access[2]);
})->with([
    'super admin' => [UserRole::SuperAdmin, [true, false, false]],
    'water provider' => [UserRole::WaterProvider, [false, true, false]],
    'consumer' => [UserRole::Consumer, [false, false, true]],
]);

test('a consumer cannot request the super admin dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard.admin'))->assertForbidden();
});

test('guests are sent to login before reaching role dashboards', function () {
    $this->get(route('dashboard.admin'))->assertRedirect(route('login'));
});

test('operators can assign a role to an existing account', function () {
    $user = User::factory()->create();

    $this->artisan('users:assign-role', ['email' => $user->email, 'role' => 'water_provider'])
        ->assertSuccessful();

    expect($user->fresh()->role)->toBe(UserRole::WaterProvider);
});

test('operators cannot assign an unknown role', function () {
    $user = User::factory()->create();

    $this->artisan('users:assign-role', ['email' => $user->email, 'role' => 'owner'])
        ->assertFailed();

    expect($user->fresh()->role)->toBe(UserRole::Consumer);
});
