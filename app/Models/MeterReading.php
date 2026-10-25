<?php

namespace App\Models;

use Database\Factories\MeterReadingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['water_provider_id', 'water_connection_id', 'water_meter_id', 'entered_by', 'reading_date', 'previous_reading', 'current_reading'])]
class MeterReading extends Model
{
    /** @use HasFactory<MeterReadingFactory> */
    use HasFactory;

    /** @return BelongsTo<WaterConnection, $this> */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(WaterConnection::class, 'water_connection_id');
    }
}
