<?php

namespace Database\Factories;

use App\Models\Connection;
use App\Models\Consumer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Connection>
 */
class ConnectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'consumer_id' => Consumer::factory(),
            'water_provider_id' => fn (array $attributes) => Consumer::query()->whereKey($attributes['consumer_id'])->value('water_provider_id'),
            'number' => fake()->unique()->bothify('CON-######'),
            'meter_number' => fake()->unique()->bothify('MTR-######'),
            'status' => 'active',
        ];
    }
}
