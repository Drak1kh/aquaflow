<?php

namespace App\Models;

use Database\Factories\BillingPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['water_provider_id', 'label', 'starts_on', 'ends_on', 'due_on'])]
class BillingPeriod extends Model
{
    /** @use HasFactory<BillingPeriodFactory> */
    use HasFactory;

    /** @return BelongsTo<WaterProvider, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(WaterProvider::class, 'water_provider_id');
    }
}
