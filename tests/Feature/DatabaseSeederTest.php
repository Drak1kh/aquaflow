<?php

use App\Models\User;
use App\UserRole;
use Database\Seeders\DatabaseSeeder;

test('the local seeder creates one account for each role and can be rerun', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    $this->assertDatabaseCount('users', 3);
    expect(User::where('email', 'admin@example.com')->firstOrFail()->role)->toBe(UserRole::SuperAdmin);
    expect(User::where('email', 'provider@example.com')->firstOrFail()->role)->toBe(UserRole::WaterProvider);
    expect(User::where('email', 'test@example.com')->firstOrFail()->role)->toBe(UserRole::Consumer);
});

test('the seeder does not promote an existing account', function () {
    $user = User::factory()->create(['email' => 'admin@example.com']);

    $this->seed(DatabaseSeeder::class);

    expect($user->fresh()->role)->toBe(UserRole::Consumer);
    $this->assertDatabaseCount('users', 3);
});
