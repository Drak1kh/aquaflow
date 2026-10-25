<?php

namespace Database\Factories;

use App\Models\Bill;
use App\Models\BillAdjustment;
use App\Models\User;
use App\Models\WaterProvider;
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
            'water_provider_id' => WaterProvider::factory(),
            'bill_id' => Bill::factory(),
            'authorized_by' => User::factory(),
            'amount' => '5.00',
            'reason' => 'Correction',
        ];
    }
}
