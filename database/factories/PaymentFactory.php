<?php

namespace Database\Factories;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Payment;
use App\Models\User;
use App\Models\WaterProvider;
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
            'water_provider_id' => WaterProvider::factory(),
            'consumer_id' => Consumer::factory(),
            'intended_bill_id' => Bill::factory(),
            'recorded_by' => User::factory(),
            'reference_number' => fake()->unique()->bothify('P-########'),
            'amount' => '25.00',
            'method' => 'cash',
            'status' => 'pending',
        ];
    }
}
