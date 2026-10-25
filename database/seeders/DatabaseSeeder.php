<?php

namespace Database\Seeders;

use App\Models\Consumer;
use App\Models\User;
use App\Models\WaterProvider;
use App\UserRole;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $provider = WaterProvider::firstOrCreate(['code' => 'demo-water'], ['name' => 'Demo Water Provider']);
        $consumer = Consumer::firstOrCreate(
            ['water_provider_id' => $provider->id, 'account_number' => 'DEMO-001'],
            ['name' => 'Test User', 'email' => 'test@example.com', 'address' => 'Demo service address'],
        );

        foreach ([
            ['name' => 'Super Admin', 'email' => 'admin@example.com', 'role' => UserRole::SuperAdmin],
            ['name' => 'Water Provider', 'email' => 'provider@example.com', 'role' => UserRole::WaterProvider, 'water_provider_id' => $provider->id],
            ['name' => 'Test User', 'email' => 'test@example.com', 'role' => UserRole::Consumer, 'consumer_id' => $consumer->id],
        ] as $account) {
            if (User::where('email', $account['email'])->doesntExist()) {
                User::factory()->create($account);
            }
        }
    }
}
