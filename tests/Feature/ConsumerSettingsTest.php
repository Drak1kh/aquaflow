<?php

use App\Models\Consumer;
use App\Models\ProviderMembership;
use App\Models\Role;
use App\Models\User;
use App\Models\WaterProvider;
use App\Notifications\AccountNotice;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Support\Facades\Notification;

test('linked consumer updates contact while provider cannot change the login email', function () {
    $this->seed(AuthorizationSeeder::class);
    $provider = WaterProvider::factory()->create();
    $user = User::factory()->create();
    $consumer = Consumer::factory()->create(['water_provider_id' => $provider->id, 'user_id' => $user->id, 'email' => $user->email]);

    $this->actingAs($user)->put(route('consumer.contact.update'), [
        'email' => 'new-contact@example.com', 'contact_number' => '09171234567',
    ])->assertSessionHasNoErrors();
    expect($user->fresh()->email)->toBe('new-contact@example.com')
        ->and($user->fresh()->email_verified_at)->toBeNull()
        ->and($consumer->fresh()->contact_number)->toBe('09171234567');

    $staff = User::factory()->create();
    ProviderMembership::factory()->create(['water_provider_id' => $provider->id, 'user_id' => $staff->id, 'role_id' => Role::where('slug', 'provider_manager')->firstOrFail()->id]);
    $this->actingAs($staff)->withSession(['active_provider_id' => $provider->id])->put(route('provider.consumers.update', $consumer), [
        'name' => $consumer->name, 'email' => 'someone-else@example.com',
        'contact_number' => $consumer->contact_number, 'service_address' => $consumer->service_address, 'status' => 'active',
    ])->assertSessionHasErrors('email');
});

test('consumer can mute routine notices while service notices remain enabled', function () {
    Notification::fake();
    $user = User::factory()->create();
    $consumer = Consumer::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->put(route('consumer.notifications.preferences'), [
        'billing' => 0, 'payment' => 0, 'support' => 0,
    ])->assertSessionHasNoErrors();

    $consumer->fresh()->notifyAccount('billing', 'Bill', 'A bill is available.');
    Notification::assertNothingSent();
    $consumer->fresh()->notifyAccount('service', 'Service', 'A disconnection notice is available.');
    Notification::assertSentTo($user, AccountNotice::class);
});
