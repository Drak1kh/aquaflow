<?php

namespace Database\Factories;

use App\Models\Bill;
use App\Models\BillingPeriod;
use App\Models\Consumer;
use App\Models\MeterReading;
use App\Models\User;
use App\Models\WaterConnection;
use App\Models\WaterProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bill>
 */
class BillFactory extends Factory
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
            'water_connection_id' => WaterConnection::factory(),
            'billing_period_id' => BillingPeriod::factory(),
            'meter_reading_id' => MeterReading::factory(),
            'generated_by' => User::factory(),
            'bill_number' => fake()->unique()->bothify('B-########'),
            'previous_reading' => '100.000',
            'current_reading' => '110.000',
            'consumption' => '10.000',
            'base_charge' => '50.00',
            'unit_rate' => '2.5000',
            'consumption_charge' => '25.00',
            'total_amount' => '75.00',
            'due_on' => now()->addMonth()->toDateString(),
            'status' => 'unpaid',
        ];
    }
}
