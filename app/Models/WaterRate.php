<?php

namespace App\Models;

use Database\Factories\WaterRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property numeric-string $base_charge
 * @property numeric-string $unit_rate
 */
#[Fillable(['water_provider_id', 'base_charge', 'unit_rate', 'effective_from'])]
class WaterRate extends Model
{
    /** @use HasFactory<WaterRateFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['base_charge' => 'decimal:2', 'unit_rate' => 'decimal:2', 'effective_from' => 'date'];
    }
}
