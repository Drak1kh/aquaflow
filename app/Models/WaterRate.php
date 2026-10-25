<?php

namespace App\Models;

use Database\Factories\WaterRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['water_provider_id', 'effective_from', 'base_charge', 'unit_rate'])]
class WaterRate extends Model
{
    /** @use HasFactory<WaterRateFactory> */
    use HasFactory;
}
