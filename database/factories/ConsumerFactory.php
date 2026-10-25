<?php

namespace Database\Factories;

use App\Models\Consumer;
use App\Models\WaterProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Consumer>
 */
class ConsumerFactory extends Factory
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
            'account_number' => fake()->unique()->bothify('ACCT-######'),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'address' => fake()->address(),
            'is_active' => true,
        ];
    }
}
