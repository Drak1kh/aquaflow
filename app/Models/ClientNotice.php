<?php

namespace App\Models;

use Database\Factories\ClientNoticeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'consumer_id', 'event_key', 'title', 'body', 'url', 'read_at'])]
class ClientNotice extends Model
{
    /** @use HasFactory<ClientNoticeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
