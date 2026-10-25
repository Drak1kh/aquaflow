<?php

namespace App\Models;

use Database\Factories\WaterProviderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'email', 'phone', 'address', 'is_active', 'due_days', 'grace_days', 'late_fee'])]
class WaterProvider extends Model
{
    /** @use HasFactory<WaterProviderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'late_fee' => 'decimal:2'];
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<Consumer, $this> */
    public function consumers(): HasMany
    {
        return $this->hasMany(Consumer::class);
    }
}
