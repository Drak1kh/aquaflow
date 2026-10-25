<?php

use App\Models\Consumer;
use App\Models\ProviderMembership;
use App\Models\User;
use App\Models\WaterConnection;
use App\Models\WaterProvider;
use Database\Seeders\AquaFlowAccountSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

test('local database seeding creates sign-in accounts for each role and provider', function () {
    config()->set('aquaflow.demo_password', 'LocalDemoPassword123!');

    $this->seed(DatabaseSeeder::class);

    expect(User::count())->toBe(5)
        ->and(WaterProvider::count())->toBe(2)
        ->and(ProviderMembership::count())->toBe(2)
        ->and(Consumer::count())->toBe(2)
        ->and(WaterConnection::count())->toBe(2);

    $admin = User::where('email', 'admin@aquaflow.test')->firstOrFail();
    $northStaff = User::where('email', 'provider.north@aquaflow.test')->firstOrFail();
    $southStaff = User::where('email', 'provider.south@aquaflow.test')->firstOrFail();
    $northConsumer = User::where('email', 'consumer.north@aquaflow.test')->firstOrFail();
    $southConsumer = User::where('email', 'consumer.south@aquaflow.test')->firstOrFail();
    $northProvider = WaterProvider::where('code', 'DEMO-NORTH')->firstOrFail();
    $southProvider = WaterProvider::where('code', 'DEMO-SOUTH')->firstOrFail();

    expect($admin->is_super_admin)->toBeTrue()
        ->and($northStaff->canInProvider('consumers.manage', $northProvider->id))->toBeTrue()
        ->and($northStaff->canInProvider('consumers.manage', $southProvider->id))->toBeFalse()
        ->and($southStaff->canInProvider('consumers.manage', $southProvider->id))->toBeTrue()
        ->and($northConsumer->consumers()->firstOrFail()->water_provider_id)->toBe($northProvider->id)
        ->and($southConsumer->consumers()->firstOrFail()->water_provider_id)->toBe($southProvider->id);

    foreach ([
        'admin@aquaflow.test' => 'admin.index',
        'provider.north@aquaflow.test' => 'provider.index',
        'consumer.north@aquaflow.test' => 'consumer.index',
    ] as $email => $workspace) {
        $this->post(route('login.store'), ['email' => $email, 'password' => 'LocalDemoPassword123!'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));
        $this->get(route('dashboard'))->assertRedirect(route($workspace));
        $this->post(route('logout'))->assertRedirect(route('home'));
    }
});

test('re-seeding keeps demo records and rotates their password', function () {
    config()->set('aquaflow.demo_password', 'OriginalPassword123!');
    $this->seed(AquaFlowAccountSeeder::class);
    config()->set('aquaflow.demo_password', 'ReplacementPassword123!');

    $this->seed(AquaFlowAccountSeeder::class);

    $admin = User::where('email', 'admin@aquaflow.test')->firstOrFail();
    expect(User::count())->toBe(5)
        ->and(WaterProvider::count())->toBe(2)
        ->and(ProviderMembership::count())->toBe(2)
        ->and(Consumer::count())->toBe(2)
        ->and(WaterConnection::count())->toBe(2)
        ->and(Hash::check('ReplacementPassword123!', $admin->password))->toBeTrue()
        ->and(Hash::check('OriginalPassword123!', $admin->password))->toBeFalse();
});

test('production seeding does not create demo accounts', function () {
    $environment = app()->environment();
    app()->detectEnvironment(fn (): string => 'production');

    try {
        $this->seed(DatabaseSeeder::class);
        $this->seed(AquaFlowAccountSeeder::class);

        expect(User::count())->toBe(0)
            ->and(WaterProvider::count())->toBe(0);
    } finally {
        app()->detectEnvironment(fn (): string => $environment);
    }
});
