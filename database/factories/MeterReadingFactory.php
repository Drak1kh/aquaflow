<?php

namespace Database\Factories;

use App\Models\Connection;
use App\Models\MeterReading;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeterReading>
 */
class MeterReadingFactory extends Factory
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
            'period_month' => now()->startOfMonth()->toDateString(),
            'previous_reading' => '0.000',
            'current_reading' => '10.000',
        ];
    }
}
