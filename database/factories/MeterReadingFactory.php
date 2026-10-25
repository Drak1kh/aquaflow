<?php

namespace Database\Factories;

use App\Models\MeterReading;
use App\Models\User;
use App\Models\WaterConnection;
use App\Models\WaterMeter;
use App\Models\WaterProvider;
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
            'water_provider_id' => WaterProvider::factory(),
            'water_connection_id' => WaterConnection::factory(),
            'water_meter_id' => WaterMeter::factory(),
            'entered_by' => User::factory(),
            'reading_date' => now()->toDateString(),
            'previous_reading' => '100.000',
            'current_reading' => '110.000',
        ];
    }
}
