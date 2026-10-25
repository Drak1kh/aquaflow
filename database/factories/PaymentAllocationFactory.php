<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentAllocation>
 */
class PaymentAllocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'bill_id' => fn (array $attributes) => Payment::query()->whereKey($attributes['payment_id'])->value('bill_id'),
            'amount' => '50.00',
        ];
    }
}
