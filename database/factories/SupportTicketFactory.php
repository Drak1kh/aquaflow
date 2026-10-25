<?php

namespace Database\Factories;

use App\Models\Consumer;
use App\Models\SupportTicket;
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
            'consumer_id' => Consumer::factory(),
            'water_provider_id' => fn (array $attributes) => Consumer::query()->whereKey($attributes['consumer_id'])->value('water_provider_id'),
            'type' => 'general',
            'subject' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => 'open',
        ];
    }
}
