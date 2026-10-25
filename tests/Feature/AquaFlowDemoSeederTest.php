<?php

use App\Models\Bill;
use App\Models\MeterReading;
use App\Models\User;
use Database\Seeders\AquaFlowDemoSeeder;
use Illuminate\Support\Facades\Hash;

test('demo seeding accepts a configured local password and does not duplicate its sample bill', function () {
    config()->set('aquaflow.demo_password', 'password');

    $this->seed(AquaFlowDemoSeeder::class);
    $this->seed(AquaFlowDemoSeeder::class);

    $bill = Bill::firstOrFail();
    expect(User::count())->toBe(5)
        ->and(MeterReading::count())->toBe(1)
        ->and(Bill::count())->toBe(1)
        ->and($bill->total_amount)->toBe('75.00')
        ->and(Hash::check('password', User::where('email', 'admin@aquaflow.test')->firstOrFail()->password))->toBeTrue();
});
