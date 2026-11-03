<?php

namespace App;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case WaterProvider = 'water_provider';
    case Consumer = 'consumer';

    public const Admin = self::SuperAdmin;

    public const Staff = self::WaterProvider;

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Admin',
            self::WaterProvider => 'Staff',
            self::Consumer => 'Consumer',
        };
    }
}
