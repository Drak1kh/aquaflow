<?php

namespace App\Models;

use Database\Factories\ConsumerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** @property-read WaterProvider|null $waterProvider */
#[Fillable(['water_provider_id', 'account_number', 'name', 'email', 'phone', 'address', 'is_active'])]
class Consumer extends Model
{
    /** @use HasFactory<ConsumerFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsTo<WaterProvider, $this> */
    public function waterProvider(): BelongsTo
    {
        return $this->belongsTo(WaterProvider::class);
    }

    /** @return HasOne<User, $this> */
    public function portalUser(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /** @return HasMany<Connection, $this> */
    public function connections(): HasMany
    {
        return $this->hasMany(Connection::class);
    }
}
