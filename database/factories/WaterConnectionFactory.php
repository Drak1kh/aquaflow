<?php

namespace Database\Factories;

use App\Models\Consumer;
use App\Models\WaterConnection;
use App\Models\WaterMeter;
use App\Models\WaterProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaterConnection>
 */
class WaterConnectionFactory extends Factory
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
            'consumer_id' => Consumer::factory(),
            'water_meter_id' => WaterMeter::factory(),
            'connection_number' => fake()->unique()->bothify('CN-######'),
            'service_address' => fake()->address(),
            'status' => 'active',
        ];
    }
}
