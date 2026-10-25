<?php

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\ProviderMembership;
use App\Models\Role;
use App\Models\User;
use App\Models\WaterProvider;
use Database\Seeders\AuthorizationSeeder;

test('provider staff cannot use another provider record', function () {
    $this->seed(AuthorizationSeeder::class);
    $own = WaterProvider::factory()->create();
    $other = WaterProvider::factory()->create();
    $staff = User::factory()->create();
    ProviderMembership::factory()->create([
        'water_provider_id' => $own->id,
        'user_id' => $staff->id,
        'role_id' => Role::where('slug', 'provider_manager')->firstOrFail()->id,
    ]);
    $foreignConsumer = Consumer::factory()->create(['water_provider_id' => $other->id]);
    $foreignBill = Bill::factory()->create(['water_provider_id' => $other->id, 'consumer_id' => $foreignConsumer->id]);

    $this->actingAs($staff)->withSession(['active_provider_id' => $own->id]);
    $this->get(route('provider.bills.show', $foreignBill))->assertNotFound();
    $this->post(route('provider.connections.store'), [
        'consumer_id' => $foreignConsumer->id,
        'connection_number' => 'CN-OWN-1',
        'meter_number' => 'M-OWN-1',
        'service_address' => 'Own street',
    ])->assertSessionHasErrors('consumer_id');
});

test('staff cannot select a provider without membership', function () {
    $own = WaterProvider::factory()->create();
    $other = WaterProvider::factory()->create();
    $staff = User::factory()->create();
    ProviderMembership::factory()->create(['water_provider_id' => $own->id, 'user_id' => $staff->id]);

    $this->actingAs($staff)->post(route('providers.switch'), ['water_provider_id' => $other->id])->assertForbidden();
});

test('reseed keeps administrator-edited role permissions', function () {
    $this->seed(AuthorizationSeeder::class);
    $role = Role::where('slug', 'provider_manager')->firstOrFail();
    $role->permissions()->detach();

    $this->seed(AuthorizationSeeder::class);

    expect($role->permissions()->count())->toBe(0);
});
