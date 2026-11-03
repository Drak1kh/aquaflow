<?php

namespace Database\Factories;

use App\Models\Connection;
use App\Models\ServiceEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceEvent>
 */
class ServiceEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'connection_id' => Connection::factory(),
            'action' => 'notice', 'reason' => 'Overdue balance',
        ];
    }
}
