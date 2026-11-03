<?php

namespace Database\Factories;

use App\Models\BillingPeriod;
use App\Models\WaterProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingPeriod>
 */
class BillingPeriodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'water_provider_id' => WaterProvider::factory(),
            'period_month' => now()->startOfMonth()->toDateString(),
            'due_at' => now()->endOfMonth()->addDays(14)->toDateString(),
        ];
    }
}
