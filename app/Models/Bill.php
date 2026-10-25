<?php

namespace App\Models;

use App\DecimalAmount;
use Database\Factories\BillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['water_provider_id', 'consumer_id', 'water_connection_id', 'billing_period_id', 'meter_reading_id', 'generated_by', 'bill_number', 'previous_reading', 'current_reading', 'consumption', 'base_charge', 'unit_rate', 'consumption_charge', 'total_amount', 'due_on', 'late_fee_applied_at', 'status'])]
class Bill extends Model
{
    /** @use HasFactory<BillFactory> */
    use HasFactory;

    /** @return BelongsTo<WaterProvider, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(WaterProvider::class, 'water_provider_id');
    }

    /** @return BelongsTo<Consumer, $this> */
    public function consumer(): BelongsTo
    {
        return $this->belongsTo(Consumer::class);
    }

    /** @return BelongsTo<WaterConnection, $this> */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(WaterConnection::class, 'water_connection_id');
    }

    /** @return BelongsTo<BillingPeriod, $this> */
    public function period(): BelongsTo
    {
        return $this->belongsTo(BillingPeriod::class, 'billing_period_id');
    }

    /** @return HasMany<BillAdjustment, $this> */
    public function adjustments(): HasMany
    {
        return $this->hasMany(BillAdjustment::class);
    }

    /** @return HasMany<PaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function balanceCents(): int
    {
        if ($this->status === 'void') {
            return 0;
        }

        $total = DecimalAmount::units((string) $this->total_amount, 2);
        $adjustments = $this->adjustments()->get()->sum(fn (BillAdjustment $adjustment): int => DecimalAmount::units((string) $adjustment->amount, 2));
        $paid = $this->allocations()->whereHas('payment', fn ($query) => $query->where('status', 'verified'))->get()
            ->sum(fn (PaymentAllocation $allocation): int => DecimalAmount::units((string) $allocation->amount, 2));

        return max(0, $total + $adjustments - $paid);
    }

    public function refreshStatus(): void
    {
        if ($this->status === 'void') {
            return;
        }

        $balance = $this->balanceCents();
        $hasVerifiedPayment = $this->allocations()->whereHas('payment', fn ($query) => $query->where('status', 'verified'))->exists();
        $status = match (true) {
            $balance === 0 => 'paid',
            $this->due_on < now()->toDateString() => 'overdue',
            $hasVerifiedPayment => 'partially_paid',
            default => 'unpaid',
        };
        $this->update(['status' => $status]);
    }
}
