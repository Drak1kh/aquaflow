<?php

namespace Database\Factories;

use App\Models\ProviderMembership;
use App\Models\Role;
use App\Models\User;
use App\Models\WaterProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderMembership>
 */
class ProviderMembershipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'water_provider_id' => WaterProvider::factory(),
            'user_id' => User::factory(),
            'role_id' => Role::factory(),
            'status' => 'active',
        ];
    }
}
