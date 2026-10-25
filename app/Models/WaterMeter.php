<?php

namespace App\Models;

use Database\Factories\WaterMeterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['water_provider_id', 'meter_number', 'type', 'status'])]
class WaterMeter extends Model
{
    /** @use HasFactory<WaterMeterFactory> */
    use HasFactory;
}
