<?php

namespace App\Models;

use Database\Factories\ServiceEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['connection_id', 'actor_id', 'action', 'reason'])]
class ServiceEvent extends Model
{
    /** @use HasFactory<ServiceEventFactory> */
    use HasFactory;

    /** @return BelongsTo<Connection, $this> */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }
}
