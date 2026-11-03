<?php

namespace App\Models;

use Database\Factories\ConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['water_provider_id', 'consumer_id', 'number', 'meter_number', 'status', 'installed_at'])]
class Connection extends Model
{
    /** @use HasFactory<ConnectionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['installed_at' => 'date'];
    }

    /** @return BelongsTo<Consumer, $this> */
    public function consumer(): BelongsTo
    {
        return $this->belongsTo(Consumer::class);
    }

    /** @return HasOne<MeterReading, $this> */
    public function latestReading(): HasOne
    {
        return $this->hasOne(MeterReading::class)->latestOfMany('period_month');
    }

    /** @return HasOne<ServiceEvent, $this> */
    public function latestServiceEvent(): HasOne
    {
        return $this->hasOne(ServiceEvent::class)->latestOfMany();
    }

    /** @return HasMany<MeterReading, $this> */
    public function readings(): HasMany
    {
        return $this->hasMany(MeterReading::class);
    }
}
