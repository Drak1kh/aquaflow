<?php

namespace Database\Factories;

use App\Models\Bill;
use App\Models\BillAdjustment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillAdjustment>
 */
class BillAdjustmentFactory extends Factory
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
            'type' => 'credit', 'amount' => '10.00', 'reason' => 'Approved correction',
        ];
    }
}
