<?php

use App\Models\Consumer;
use App\Models\ProviderMembership;
use App\Models\Role;
use App\Models\User;
use App\Models\WaterProvider;
use Database\Seeders\AuthorizationSeeder;

test('each role can open its workspace pages with empty operational data', function () {
    $this->seed(AuthorizationSeeder::class);
    $provider = WaterProvider::factory()->create();
    $staff = User::factory()->create();
    ProviderMembership::factory()->create([
        'water_provider_id' => $provider->id,
        'user_id' => $staff->id,
        'role_id' => Role::where('slug', 'provider_manager')->firstOrFail()->id,
    ]);
    $consumerUser = User::factory()->create();
    Consumer::factory()->create(['water_provider_id' => $provider->id, 'user_id' => $consumerUser->id]);
    $admin = User::factory()->create(['is_super_admin' => true]);

    $this->actingAs($staff)->withSession(['active_provider_id' => $provider->id]);
    foreach (['provider.index', 'provider.billing', 'provider.payments', 'provider.service', 'provider.report', 'provider.support'] as $name) {
        $this->get(route($name))->assertOk();
    }

    $this->actingAs($consumerUser)->get(route('consumer.index'))->assertOk();
    $this->get(route('consumer.support'))->assertOk();

    $this->actingAs($admin)->get(route('admin.index'))->assertOk();
    $this->get(route('admin.overview'))->assertOk();
    $this->get(route('admin.settings'))->assertOk();
    $this->get(route('admin.support'))->assertOk();
});
