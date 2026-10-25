<?php

namespace App\Models;

use Database\Factories\BillAdjustmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['water_provider_id', 'bill_id', 'authorized_by', 'amount', 'reason'])]
class BillAdjustment extends Model
{
    /** @use HasFactory<BillAdjustmentFactory> */
    use HasFactory;

    /** @return BelongsTo<Bill, $this> */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }
}
