<?php

namespace App\Actions;

use App\Models\SystemSetting;
use App\Models\WaterProvider;

class PlatformSettings
{
    /** @var array<string, string> */
    private array $values = [];

    /** @var array<string, string> */
    public const DEFAULTS = [
        'platform_name' => 'AquaFlow',
        'due_days' => '14',
        'grace_days' => '7',
        'late_fee' => '0.00',
        'date_format' => 'M j, Y',
        'email_notifications' => '0',
    ];

    public function get(string $key): string
    {
        return $this->values[$key] ??= (string) (SystemSetting::whereKey($key)->value('value') ?? self::DEFAULTS[$key]);
    }

    /** @return numeric-string */
    public function numberForProvider(WaterProvider $provider, string $key): string
    {
        $value = (string) ($provider->{$key} ?? $this->get($key));

        if (! is_numeric($value)) {
            throw new \UnexpectedValueException("Setting {$key} must be numeric.");
        }

        return $value;
    }

    public function put(string $key, string $value): void
    {
        SystemSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        $this->values[$key] = $value;
    }
}
