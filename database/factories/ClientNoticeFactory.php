<?php

namespace Database\Factories;

use App\Models\ClientNotice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientNotice>
 */
class ClientNoticeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'consumer_id' => fn (array $attributes) => User::findOrFail($attributes['user_id'])->consumer_id,
            'event_key' => fake()->unique()->uuid(),
            'title' => 'Payment due reminder',
            'body' => 'Your water bill is ready for review.',
            'url' => '/portal/bills',
            'read_at' => null,
        ];
    }
}
