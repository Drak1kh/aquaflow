<?php

namespace App\Models;

use App\Notifications\AccountNotice;
use Database\Factories\ConsumerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['water_provider_id', 'user_id', 'account_number', 'name', 'email', 'contact_number', 'service_address', 'status', 'notification_preferences'])]
class Consumer extends Model
{
    /** @use HasFactory<ConsumerFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['notification_preferences' => 'array'];
    }

    public function notifyAccount(string $category, string $title, string $message): void
    {
        if ($this->user && $this->notificationEnabled($category)) {
            $this->user->notify(new AccountNotice($title, $message));
        }
    }

    public function notificationEnabled(string $category): bool
    {
        if ($category === 'service') {
            return true;
        }

        $preferences = $this->notification_preferences ?? [];

        return (bool) ($preferences[$category] ?? (SystemSetting::setting('notification_'.$category.'_default', '1') === '1'));
    }

    /** @return BelongsTo<WaterProvider, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(WaterProvider::class, 'water_provider_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<WaterConnection, $this> */
    public function connections(): HasMany
    {
        return $this->hasMany(WaterConnection::class);
    }

    /** @return HasMany<Bill, $this> */
    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }
}
