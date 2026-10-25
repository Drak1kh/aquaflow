<?php

namespace App\Models;

use Database\Factories\ProviderMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['water_provider_id', 'user_id', 'role_id', 'status'])]
class ProviderMembership extends Model
{
    /** @use HasFactory<ProviderMembershipFactory> */
    use HasFactory;

    /** @return BelongsTo<WaterProvider, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(WaterProvider::class, 'water_provider_id');
    }

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
