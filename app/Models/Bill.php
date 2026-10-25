<?php

namespace App\Models;

use Database\Factories\BillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property numeric-string $total
 * @property numeric-string $late_fee
 * @property numeric-string $base_charge
 * @property numeric-string $unit_rate
 * @property numeric-string $consumption_charge
 * @property numeric-string $previous_reading
 * @property numeric-string $current_reading
 * @property numeric-string $consumption
 * @property Carbon $due_at
 * @property Carbon $period_month
 * @property-read WaterProvider $waterProvider
 * @property-read Consumer $consumer
 */
#[Fillable(['water_provider_id', 'consumer_id', 'connection_id', 'meter_reading_id', 'number', 'period_month', 'previous_reading', 'current_reading', 'consumption', 'base_charge', 'unit_rate', 'consumption_charge', 'late_fee', 'total', 'due_at', 'status', 'late_fee_applied_at'])]
class Bill extends Model
{
    /** @use HasFactory<BillFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'period_month' => 'date', 'due_at' => 'date', 'late_fee_applied_at' => 'datetime',
            'previous_reading' => 'decimal:3', 'current_reading' => 'decimal:3', 'consumption' => 'decimal:3',
            'base_charge' => 'decimal:2', 'unit_rate' => 'decimal:2', 'consumption_charge' => 'decimal:2',
            'late_fee' => 'decimal:2', 'total' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Consumer, $this> */
    public function consumer(): BelongsTo
    {
        return $this->belongsTo(Consumer::class);
    }

    /** @return BelongsTo<WaterProvider, $this> */
    public function waterProvider(): BelongsTo
    {
        return $this->belongsTo(WaterProvider::class);
    }

    /** @return HasMany<PaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /** @return numeric-string */
    public function balance(): string
    {
        $paid = '0.00';
        foreach ($this->allocations as $allocation) {
            $paid = bcadd($paid, $allocation->amount, 2);
        }

        return bcsub($this->total, $paid, 2);
    }
}
