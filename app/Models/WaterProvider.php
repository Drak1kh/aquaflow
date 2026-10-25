<?php

namespace App\Models;

use Database\Factories\WaterProviderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'status', 'email', 'contact_number', 'office_address', 'grace_days', 'disconnection_days', 'late_fee', 'reconnection_fee'])]
class WaterProvider extends Model
{
    /** @use HasFactory<WaterProviderFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'active', 'grace_days' => 0, 'disconnection_days' => 30, 'late_fee' => '0.00', 'reconnection_fee' => '0.00'];

    /** @return HasMany<ProviderMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(ProviderMembership::class);
    }

    /** @return HasMany<Consumer, $this> */
    public function consumers(): HasMany
    {
        return $this->hasMany(Consumer::class);
    }

    /** @return HasMany<Bill, $this> */
    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
