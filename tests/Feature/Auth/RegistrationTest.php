<?php

use App\Models\Consumer;
use App\Models\User;
use App\Models\WaterProvider;

test('public registration is unavailable', function () {
    $this->get('/register')->assertNotFound();
});

test('public registration cannot create an unlinked consumer account', function () {
    $this->post('/register', [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
});

test('a provider can activate portal access for its consumer', function () {
    $provider = WaterProvider::create(['name' => 'North Water', 'code' => 'north']);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $provider->id]);
    $consumer = Consumer::create(['water_provider_id' => $provider->id, 'account_number' => 'A-001', 'name' => 'John Doe', 'address' => 'North Street']);

    $this->actingAs($staff)->post(route('provider.consumers.portal', $consumer), [
        'email' => 'john@example.com', 'password' => 'temporary-long-password',
    ])->assertSessionHasNoErrors();

    $portalUser = User::where('email', 'john@example.com')->firstOrFail();
    expect($portalUser->consumer_id)->toBe($consumer->id);
    expect($portalUser->must_change_password)->toBeTrue();
});
