<?php

namespace Database\Factories;

use App\Models\WaterMeter;
use App\Models\WaterProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaterMeter>
 */
class WaterMeterFactory extends Factory
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
            'meter_number' => fake()->unique()->bothify('M-######'),
            'type' => 'standard',
            'status' => 'active',
        ];
    }
}
