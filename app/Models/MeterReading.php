<?php

namespace App\Models;

use Database\Factories\MeterReadingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property numeric-string $previous_reading
 * @property numeric-string $current_reading
 */
#[Fillable(['connection_id', 'water_provider_id', 'period_month', 'previous_reading', 'current_reading', 'recorded_by'])]
class MeterReading extends Model
{
    /** @use HasFactory<MeterReadingFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['period_month' => 'date', 'previous_reading' => 'decimal:3', 'current_reading' => 'decimal:3'];
    }

    /** @return BelongsTo<Connection, $this> */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }
}
