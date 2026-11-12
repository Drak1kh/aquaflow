<?php

namespace Database\Seeders;

use App\Models\Consumer;
use App\Models\User;
use App\Models\WaterProvider;
use App\UserRole;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $provider = WaterProvider::firstOrCreate(['code' => 'demo-water'], [
            'name' => 'AquaFlow Waterworks', 'email' => 'provider@example.com', 'phone' => '0917 000 0100',
            'address' => 'AquaFlow office', 'due_days' => 14, 'grace_days' => 7, 'late_fee' => '25.00', 'is_active' => true,
        ]);
        $consumer = Consumer::firstOrCreate(['water_provider_id' => $provider->id, 'account_number' => 'DEMO-001'], [
            'name' => 'Consumer Account', 'email' => 'test@example.com', 'phone' => '0917 555 0001', 'address' => 'Consumer address', 'is_active' => true,
        ]);

        $this->account(['name' => 'AquaFlow Administrator', 'email' => 'admin@example.com', 'role' => UserRole::SuperAdmin]);
        $this->account(['name' => 'AquaFlow Provider', 'email' => 'provider@example.com', 'role' => UserRole::WaterProvider, 'water_provider_id' => $provider->id]);
        $this->account(['name' => 'Consumer Account', 'email' => 'test@example.com', 'role' => UserRole::Consumer, 'consumer_id' => $consumer->id]);
    }

    /** @param array<string, mixed> $attributes */
    private function account(array $attributes): void
    {
        if (! User::where('email', $attributes['email'])->exists()) {
            User::factory()->create($attributes);
        }
    }
}
