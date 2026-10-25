<?php

namespace Database\Factories;

use App\Models\Bill;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bill_id' => Bill::factory(),
            'water_provider_id' => fn (array $attributes) => Bill::query()->whereKey($attributes['bill_id'])->value('water_provider_id'),
            'consumer_id' => fn (array $attributes) => Bill::query()->whereKey($attributes['bill_id'])->value('consumer_id'),
            'reference' => fake()->unique()->bothify('PAY-########'),
            'amount' => '50.00',
            'method' => 'cash',
            'status' => 'pending',
            'paid_at' => now()->toDateString(),
        ];
    }
}
