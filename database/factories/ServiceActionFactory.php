<?php

namespace Database\Factories;

use App\Models\ServiceAction;
use App\Models\User;
use App\Models\WaterConnection;
use App\Models\WaterProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceAction>
 */
class ServiceActionFactory extends Factory
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
            'water_connection_id' => WaterConnection::factory(),
            'performed_by' => User::factory(),
            'action' => 'notice',
            'notes' => fake()->sentence(),
        ];
    }
}
