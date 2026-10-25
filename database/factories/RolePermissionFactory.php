<?php

namespace Database\Factories;

use App\Models\RolePermission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RolePermission>
 */
class RolePermissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role' => 'water_provider',
            'permission' => fake()->unique()->slug(2),
            'allowed' => true,
        ];
    }
}
