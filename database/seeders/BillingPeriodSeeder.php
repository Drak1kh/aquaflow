<?php

namespace Database\Seeders;

use App\Models\BillingPeriod;
use App\Models\WaterProvider;
use Illuminate\Database\Seeder;

class BillingPeriodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        WaterProvider::each(function (WaterProvider $provider): void {
            BillingPeriod::firstOrCreate(
                ['water_provider_id' => $provider->id, 'period_month' => now()->startOfMonth()->startOfDay()],
                ['due_at' => now()->endOfMonth()->addDays($provider->due_days ?? 14)->toDateString()],
            );
        });
    }
}
