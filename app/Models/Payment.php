<?php

namespace App\Models;

use Closure;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property numeric-string $amount
 * @property Carbon $paid_at
 * @property Carbon|null $checked_at
 * @property-read WaterProvider $waterProvider
 * @property-read Consumer $consumer
 * @property-read Bill $bill
 */
#[Fillable(['water_provider_id', 'consumer_id', 'bill_id', 'reference', 'amount', 'method', 'status', 'paid_at', 'verified_by', 'verified_at', 'checked_at', 'remarks', 'proof_path'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'date', 'verified_at' => 'datetime', 'checked_at' => 'datetime', 'receipt_snapshot' => 'array'];
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

    /** @return HasMany<PaymentBillItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PaymentBillItem::class)->orderBy('id');
    }

    /** @return Collection<int, Bill> */
    public function coveredBills(): Collection
    {
        $associations = $this->status === 'verified' ? $this->allocations : $this->items;
        $bills = $associations->isNotEmpty() ? $associations->pluck('bill') : collect([$this->bill]);

        return $bills->filter(fn (?Bill $bill) => $bill && $bill->consumer_id === $this->consumer_id && $bill->water_provider_id === $this->water_provider_id)
            ->sortBy(fn (Bill $bill) => $bill->period_month->toDateString().str_pad((string) $bill->id, 20, '0', STR_PAD_LEFT))->values();
    }

    public function amountForBill(Bill $bill): string
    {
        if (! $this->coveredBills()->contains('id', $bill->id)) {
            return '0.00';
        }
        $associations = $this->status === 'verified' ? $this->allocations : $this->items;

        return $associations->isNotEmpty() ? ($associations->firstWhere('bill_id', $bill->id)?->amount ?? '0.00') : $this->amount;
    }

    public function scopeWhereHasCoveredBill(Builder $query, Closure $constraint): void
    {
        $authorized = fn ($bill) => $bill->whereColumn('bills.consumer_id', 'payments.consumer_id')->whereColumn('bills.water_provider_id', 'payments.water_provider_id')->where($constraint);
        $query->where(function ($query) use ($authorized) {
            $query->where(fn ($query) => $query->where('status', 'verified')->whereHas('allocations.bill', $authorized))
                ->orWhere(fn ($query) => $query->where('status', '!=', 'verified')->whereHas('items.bill', $authorized))
                ->orWhere(fn ($query) => $query->where(function ($query) {
                    $query->where(fn ($query) => $query->where('status', 'verified')->whereDoesntHave('allocations'))
                        ->orWhere(fn ($query) => $query->where('status', '!=', 'verified')->whereDoesntHave('items'));
                })->whereHas('bill', $authorized));
        });
    }
}
