<?php

namespace Database\Factories;

use App\Models\WaterProvider;
use App\Models\WaterRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaterRate>
 */
class WaterRateFactory extends Factory
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
            'base_charge' => '50.00',
            'unit_rate' => '10.00',
            'effective_from' => now()->startOfMonth()->toDateString(),
        ];
    }
}
