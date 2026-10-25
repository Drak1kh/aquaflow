<?php

namespace App\Models;

use Database\Factories\SupportTicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read WaterProvider $waterProvider
 * @property-read Consumer $consumer
 */
#[Fillable(['water_provider_id', 'consumer_id', 'opened_by', 'assigned_to', 'type', 'subject', 'description', 'status', 'escalated_at', 'resolved_at', 'resolution'])]
class SupportTicket extends Model
{
    /** @use HasFactory<SupportTicketFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['escalated_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    /** @return BelongsTo<Consumer, $this> */
    public function consumer(): BelongsTo
    {
        return $this->belongsTo(Consumer::class);
    }

    /** @return BelongsTo<WaterProvider, $this> */
    public function waterProvider(): BelongsTo
    {
        return $this->belongsTo(WaterProvider::class);
    }

    /** @return HasMany<TicketMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }
}
