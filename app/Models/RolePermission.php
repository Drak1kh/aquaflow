<?php

namespace App\Models;

use Database\Factories\RolePermissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['role', 'permission', 'allowed'])]
class RolePermission extends Model
{
    /** @use HasFactory<RolePermissionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['allowed' => 'boolean'];
    }
}
