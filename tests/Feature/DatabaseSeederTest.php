<?php

use App\Models\Consumer;
use App\Models\User;
use App\Models\WaterProvider;
use App\UserRole;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\Hash;

test('the local seeder creates only the three role credentials without operational data', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(User::count())->toBe(3)
        ->and(WaterProvider::count())->toBe(1)
        ->and(Consumer::count())->toBe(1);
    $this->assertDatabaseCount('connections', 0);
    $this->assertDatabaseCount('meter_readings', 0);
    $this->assertDatabaseCount('bills', 0);
    $this->assertDatabaseCount('payments', 0);

    foreach ([
        'admin@example.com' => UserRole::SuperAdmin,
        'provider@example.com' => UserRole::WaterProvider,
        'test@example.com' => UserRole::Consumer,
    ] as $email => $role) {
        $user = User::where('email', $email)->firstOrFail();
        expect($user->role)->toBe($role)->and(Hash::check('password', $user->password))->toBeTrue();
    }
});

test('the credential seeder does not alter an existing account', function () {
    $user = User::factory()->create(['email' => 'admin@example.com']);

    $this->seed(DatabaseSeeder::class);

    expect($user->fresh()->role)->toBe(UserRole::Consumer);
});

test('credential seeders leave production databases unchanged', function (string $seeder) {
    $environment = app()->environment();
    app()->instance('env', 'production');

    try {
        app($seeder)->run();
    } finally {
        app()->instance('env', $environment);
    }

    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('water_providers', 0);
})->with([DatabaseSeeder::class, DemoDataSeeder::class]);
