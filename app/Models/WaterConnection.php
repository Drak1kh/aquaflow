<?php

namespace App\Models;

use Database\Factories\WaterConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['water_provider_id', 'consumer_id', 'water_meter_id', 'connection_number', 'service_address', 'installed_at', 'status'])]
class WaterConnection extends Model
{
    /** @use HasFactory<WaterConnectionFactory> */
    use HasFactory;

    /** @return BelongsTo<Consumer, $this> */
    public function consumer(): BelongsTo
    {
        return $this->belongsTo(Consumer::class);
    }

    /** @return BelongsTo<WaterMeter, $this> */
    public function meter(): BelongsTo
    {
        return $this->belongsTo(WaterMeter::class, 'water_meter_id');
    }

    /** @return HasMany<MeterReading, $this> */
    public function readings(): HasMany
    {
        return $this->hasMany(MeterReading::class);
    }

    /** @return HasMany<Bill, $this> */
    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }
}
