<?php

namespace App\Actions;

use App\Models\Bill;
use App\Models\BillingPeriod;
use App\Models\Connection;
use App\Models\MeterReading;
use App\Models\User;
use App\Models\WaterProvider;
use App\Models\WaterRate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillingService
{
    public function __construct(private PlatformSettings $settings, private AuditLogger $audit) {}

    /** @return array{reading: MeterReading, rate: WaterRate, consumption: string, consumption_charge: string, total: string, due_at: string} */
    public function preview(Connection $connection, string $period): array
    {
        $month = CarbonImmutable::parse($period)->startOfMonth();
        $reading = MeterReading::where('connection_id', $connection->id)->whereDate('period_month', $month->toDateString())->first();
        $rate = WaterRate::where('water_provider_id', $connection->water_provider_id)
            ->whereDate('effective_from', '<=', $month->endOfMonth()->toDateString())
            ->latest('effective_from')->first();

        if ($reading === null || $rate === null) {
            throw ValidationException::withMessages(['period' => 'A meter reading and effective water rate are required for this month.']);
        }

        if (bccomp($reading->current_reading, $reading->previous_reading, 3) < 0) {
            throw ValidationException::withMessages(['period' => 'The current reading cannot be lower than the previous reading.']);
        }

        $consumption = bcsub($reading->current_reading, $reading->previous_reading, 3);
        $consumptionCharge = bcmul($consumption, $rate->unit_rate, 2);
        $provider = WaterProvider::findOrFail($connection->water_provider_id);

        return [
            'reading' => $reading,
            'rate' => $rate,
            'consumption' => $consumption,
            'consumption_charge' => $consumptionCharge,
            'total' => bcadd($rate->base_charge, $consumptionCharge, 2),
            'due_at' => BillingPeriod::where('water_provider_id', $provider->id)->whereDate('period_month', $month->toDateString())->first()?->due_at->toDateString()
                ?? $month->endOfMonth()->addDays((int) $this->settings->numberForProvider($provider, 'due_days'))->toDateString(),
        ];
    }

    public function generate(Connection $connection, string $period, User $actor): Bill
    {
        return DB::transaction(function () use ($connection, $period, $actor): Bill {
            $connection = Connection::whereKey($connection->id)->lockForUpdate()->firstOrFail();
            $month = CarbonImmutable::parse($period)->startOfMonth()->toDateString();

            if ($connection->status !== 'active' || Bill::where('connection_id', $connection->id)->whereDate('period_month', $month)->exists()) {
                throw ValidationException::withMessages(['period' => 'This connection is inactive or already billed for the month.']);
            }

            $preview = $this->preview($connection, $month);
            $bill = Bill::create([
                'water_provider_id' => $connection->water_provider_id,
                'consumer_id' => $connection->consumer_id,
                'connection_id' => $connection->id,
                'meter_reading_id' => $preview['reading']->id,
                'number' => 'B-'.str_replace('-', '', substr($month, 0, 7)).'-'.$connection->id,
                'period_month' => $month,
                'previous_reading' => $preview['reading']->previous_reading,
                'current_reading' => $preview['reading']->current_reading,
                'consumption' => $preview['consumption'],
                'base_charge' => $preview['rate']->base_charge,
                'unit_rate' => $preview['rate']->unit_rate,
                'consumption_charge' => $preview['consumption_charge'],
                'total' => $preview['total'],
                'due_at' => $preview['due_at'],
            ]);
            $this->audit->record($bill, 'bill.generated', $actor, after: ['total' => $bill->total]);

            return $bill;
        });
    }

    public function applyLateFee(Bill $bill): void
    {
        DB::transaction(function () use ($bill): void {
            $bill = Bill::whereKey($bill->id)->lockForUpdate()->firstOrFail();
            $provider = WaterProvider::findOrFail($bill->water_provider_id);
            $deadline = $bill->due_at->addDays((int) $this->settings->numberForProvider($provider, 'grace_days'));

            if ($bill->status === 'void' || $bill->late_fee_applied_at !== null || today()->lessThanOrEqualTo($deadline) || bccomp($bill->balance(), '0.00', 2) <= 0) {
                return;
            }

            $fee = $this->settings->numberForProvider($provider, 'late_fee');
            $bill->late_fee = $fee;
            $bill->total = bcadd($bill->total, $fee, 2);
            $bill->status = 'overdue';
            $bill->late_fee_applied_at = now();
            $bill->save();
            $this->audit->record($bill, 'bill.overdue', null, after: ['late_fee' => $fee]);
        });
    }
}
