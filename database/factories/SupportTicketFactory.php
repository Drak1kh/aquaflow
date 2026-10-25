<?php

namespace Database\Factories;

use App\Models\Consumer;
use App\Models\SupportTicket;
use App\Models\WaterProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicket>
 */
class SupportTicketFactory extends Factory
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
            'type' => 'billing',
            'subject' => fake()->sentence(),
            'status' => 'open',
        ];
    }
}
