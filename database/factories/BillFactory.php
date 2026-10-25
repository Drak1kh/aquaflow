<?php

namespace Database\Factories;

use App\Models\Bill;
use App\Models\Connection;
use App\Models\MeterReading;
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
            'connection_id' => Connection::factory(),
            'water_provider_id' => fn (array $attributes) => Connection::query()->whereKey($attributes['connection_id'])->value('water_provider_id'),
            'consumer_id' => fn (array $attributes) => Connection::query()->whereKey($attributes['connection_id'])->value('consumer_id'),
            'meter_reading_id' => fn (array $attributes) => MeterReading::factory()->create(['connection_id' => $attributes['connection_id'], 'water_provider_id' => $attributes['water_provider_id']])->id,
            'number' => fake()->unique()->bothify('B-########'),
            'period_month' => now()->startOfMonth()->toDateString(),
            'previous_reading' => '0.000',
            'current_reading' => '10.000',
            'consumption' => '10.000',
            'base_charge' => '50.00',
            'unit_rate' => '10.00',
            'consumption_charge' => '100.00',
            'late_fee' => '0.00',
            'total' => '150.00',
            'due_at' => now()->addMonth()->toDateString(),
            'status' => 'unpaid',
        ];
    }
}
