<?php

namespace App;

use App\Models\Bill;
use App\Models\BillingPeriod;
use App\Models\MeterReading;
use App\Models\WaterConnection;
use App\Models\WaterRate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BillingCalculator
{
    /**
     * @return array{previous_reading: string, current_reading: string, consumption: string, base_charge: string, unit_rate: string, consumption_charge: string, total_amount: string}
     */
    public function preview(int $providerId, BillingPeriod $period, MeterReading $reading): array
    {
        $connection = $reading->connection;
        if ($period->water_provider_id !== $providerId || $reading->water_provider_id !== $providerId || $connection->water_provider_id !== $providerId) {
            abort(404);
        }

        if ($reading->reading_date < $period->starts_on || $reading->reading_date > $period->ends_on) {
            throw ValidationException::withMessages(['reading' => 'The reading must be within the billing period.']);
        }

        $rate = WaterRate::where('water_provider_id', $providerId)->where('effective_from', '<=', $period->ends_on)->latest('effective_from')->first();
        if (! $rate) {
            throw ValidationException::withMessages(['rate' => 'Configure a rate before generating bills.']);
        }

        $consumption = DecimalAmount::units((string) $reading->current_reading, 3) - DecimalAmount::units((string) $reading->previous_reading, 3);
        if ($consumption < 0) {
            throw ValidationException::withMessages(['reading' => 'Current reading cannot be below the previous reading.']);
        }

        $consumptionCents = intdiv(($consumption * DecimalAmount::units((string) $rate->unit_rate, 4)) + 50000, 100000);
        $baseCents = DecimalAmount::units((string) $rate->base_charge, 2);

        return [
            'previous_reading' => (string) $reading->previous_reading,
            'current_reading' => (string) $reading->current_reading,
            'consumption' => DecimalAmount::format($consumption, 3),
            'base_charge' => (string) $rate->base_charge,
            'unit_rate' => (string) $rate->unit_rate,
            'consumption_charge' => DecimalAmount::format($consumptionCents),
            'total_amount' => DecimalAmount::format($baseCents + $consumptionCents),
        ];
    }

    public function generate(int $providerId, BillingPeriod $period, MeterReading $reading, int $userId): Bill
    {
        return DB::transaction(function () use ($providerId, $period, $reading, $userId): Bill {
            $connection = WaterConnection::whereKey($reading->water_connection_id)->lockForUpdate()->firstOrFail();

            if (Bill::where('water_connection_id', $connection->id)->where('billing_period_id', $period->id)->exists()) {
                throw ValidationException::withMessages(['reading' => 'This connection already has a bill for the period.']);
            }

            $snapshot = $this->preview($providerId, $period, $reading);

            return Bill::create([
                'water_provider_id' => $providerId,
                'consumer_id' => $connection->consumer_id,
                'water_connection_id' => $connection->id,
                'billing_period_id' => $period->id,
                'meter_reading_id' => $reading->id,
                'generated_by' => $userId,
                'bill_number' => 'B-'.Str::upper(Str::random(12)),
                ...$snapshot,
                'due_on' => $period->due_on,
                'status' => 'unpaid',
            ]);
        });
    }
}
