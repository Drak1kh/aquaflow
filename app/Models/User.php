<?php

namespace App\Models;

use App\Actions\PermissionRegistry;
use App\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property UserRole $role
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    protected $attributes = [
        'is_active' => true,
        'must_change_password' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    public function hasRole(UserRole $role): bool
    {
        return $this->role === $role;
    }

    /** @return BelongsTo<WaterProvider, $this> */
    public function waterProvider(): BelongsTo
    {
        return $this->belongsTo(WaterProvider::class);
    }

    /** @return BelongsTo<Consumer, $this> */
    public function consumer(): BelongsTo
    {
        return $this->belongsTo(Consumer::class);
    }

    public function hasPermission(string $permission): bool
    {
        if (! $this->is_active || ! array_key_exists($permission, PermissionRegistry::LABELS)) {
            return false;
        }

        if ($this->role === UserRole::SuperAdmin) {
            return true;
        }

        if (! in_array($permission, PermissionRegistry::DEFAULTS[$this->role->value], true)) {
            return false;
        }

        if ($this->role === UserRole::WaterProvider && (! $this->water_provider_id || ! $this->waterProvider?->is_active)) {
            return false;
        }

        if ($this->role === UserRole::Consumer && (! $this->consumer_id || ! $this->consumer?->is_active || ! $this->consumer->waterProvider?->is_active)) {
            return false;
        }

        $override = RolePermission::where('role', $this->role->value)->where('permission', $permission)->first();

        if ($override !== null) {
            return $override->allowed;
        }

        return in_array($permission, PermissionRegistry::DEFAULTS[$this->role->value], true);
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
