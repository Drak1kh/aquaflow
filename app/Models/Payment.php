<?php

namespace App\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property numeric-string $amount
 * @property Carbon $paid_at
 * @property-read WaterProvider $waterProvider
 * @property-read Consumer $consumer
 * @property-read Bill $bill
 */
#[Fillable(['water_provider_id', 'consumer_id', 'bill_id', 'reference', 'amount', 'method', 'status', 'paid_at', 'verified_by', 'verified_at', 'remarks', 'proof_path'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'date', 'verified_at' => 'datetime'];
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

    /** @return BelongsTo<Bill, $this> */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    /** @return HasMany<PaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }
}
