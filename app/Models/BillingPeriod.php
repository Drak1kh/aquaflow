<?php

namespace App\Models;

use Database\Factories\BillingPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['water_provider_id', 'period_month', 'due_at'])]
class BillingPeriod extends Model
{
    /** @use HasFactory<BillingPeriodFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['period_month' => 'date', 'due_at' => 'date'];
    }
}
