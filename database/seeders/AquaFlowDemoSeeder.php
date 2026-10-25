<?php

namespace Database\Seeders;

use App\BillingCalculator;
use App\Models\Bill;
use App\Models\BillingPeriod;
use App\Models\Consumer;
use App\Models\MeterReading;
use App\Models\User;
use App\Models\WaterConnection;
use App\Models\WaterMeter;
use App\Models\WaterProvider;
use App\Models\WaterRate;
use Illuminate\Database\Seeder;

class AquaFlowDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Demo data is available only in local and testing environments.');

            return;
        }

        $this->call(AquaFlowAccountSeeder::class);
        $provider = WaterProvider::where('code', 'DEMO-NORTH')->firstOrFail();
        $staff = User::where('email', 'provider.north@aquaflow.test')->firstOrFail();
        $consumer = Consumer::where('water_provider_id', $provider->id)->where('account_number', 'DEMO-NORTH-001')->firstOrFail();
        $connection = WaterConnection::where('water_provider_id', $provider->id)->where('consumer_id', $consumer->id)->firstOrFail();
        $meter = WaterMeter::findOrFail($connection->water_meter_id);
        $today = now();
        $startsOn = $today->copy()->startOfMonth()->toDateString();
        $endsOn = $today->copy()->endOfMonth()->toDateString();

        $reading = MeterReading::firstOrCreate(
            ['water_connection_id' => $connection->id, 'reading_date' => $today->toDateString()],
            [
                'water_provider_id' => $provider->id,
                'water_meter_id' => $meter->id,
                'entered_by' => $staff->id,
                'previous_reading' => '100.000',
                'current_reading' => '110.000',
            ],
        );
        WaterRate::firstOrCreate(
            ['water_provider_id' => $provider->id, 'effective_from' => $startsOn],
            ['base_charge' => '50.00', 'unit_rate' => '2.5000'],
        );
        $period = BillingPeriod::firstOrCreate(
            ['water_provider_id' => $provider->id, 'starts_on' => $startsOn, 'ends_on' => $endsOn],
            ['label' => $today->format('F Y'), 'due_on' => $today->copy()->addMonth()->toDateString()],
        );

        if (! Bill::where('water_connection_id', $connection->id)->where('billing_period_id', $period->id)->exists()) {
            app(BillingCalculator::class)->generate($provider->id, $period, $reading, $staff->id);
        }
    }
}
