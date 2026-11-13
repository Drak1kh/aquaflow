<?php

use App\Models\ClientNotice;
use App\Models\Consumer;
use App\Models\RolePermission;
use App\Models\User;
use Livewire\Livewire;

test('the header contains a notification sheet with only the current consumer inbox', function () {
    $consumer = Consumer::factory()->create();
    $client = User::factory()->create(['consumer_id' => $consumer->id]);
    ClientNotice::factory()->create(['user_id' => $client->id, 'title' => 'Your latest bill']);
    ClientNotice::factory()->create(['title' => 'Another account notice']);
    ClientNotice::factory()->create(['user_id' => $client->id, 'consumer_id' => Consumer::factory(), 'title' => 'Previous consumer notice']);

    $this->actingAs($client)->get(route('portal.section', 'account'))
        ->assertOk()->assertSee('data-modal="header-notifications"', false)
        ->assertSee('Your latest bill')->assertDontSee('Another account notice')->assertDontSee('Previous consumer notice');
});

test('consumers filter and mark notifications read inside the sheet', function () {
    $client = User::factory()->create(['consumer_id' => Consumer::factory()]);
    $unread = ClientNotice::factory()->create(['user_id' => $client->id, 'title' => 'Unread bill']);
    ClientNotice::factory()->create(['user_id' => $client->id, 'title' => 'Read payment', 'read_at' => now()]);
    $other = ClientNotice::factory()->create();

    Livewire::actingAs($client)->test('notification-sheet')
        ->assertSee('Unread bill')->assertSee('Read payment')
        ->call('toggleUnread')->assertSee('Unread bill')->assertDontSee('Read payment')
        ->call('markAsRead', $unread->id)->assertSee('No unread notifications.')
        ->call('toggleUnread')->assertSee('Read payment');

    expect($unread->fresh()->read_at)->not->toBeNull();
    expect($other->fresh()->read_at)->toBeNull();
});

test('marking all notifications read excludes other accounts and previous consumers', function () {
    $client = User::factory()->create(['consumer_id' => Consumer::factory()]);
    $own = ClientNotice::factory()->create(['user_id' => $client->id]);
    $previous = ClientNotice::factory()->create(['user_id' => $client->id, 'consumer_id' => Consumer::factory()]);
    $other = ClientNotice::factory()->create();

    Livewire::actingAs($client)->test('notification-sheet')->call('markAllAsRead')->assertHasNoErrors();

    expect($own->fresh()->read_at)->not->toBeNull();
    expect($previous->fresh()->read_at)->toBeNull();
    expect($other->fresh()->read_at)->toBeNull();
});

test('the sheet rejects marking a notification outside the current inbox', function () {
    $client = User::factory()->create(['consumer_id' => Consumer::factory()]);
    $other = ClientNotice::factory()->create();

    Livewire::actingAs($client)->test('notification-sheet')->call('markAsRead', $other->id)->assertNotFound();

    expect($other->fresh()->read_at)->toBeNull();
});

test('notification permission is required to render the consumer sheet', function () {
    $client = User::factory()->create(['consumer_id' => Consumer::factory()]);
    RolePermission::factory()->create(['role' => 'consumer', 'permission' => 'notifications.view', 'allowed' => false]);

    $this->actingAs($client)->get(route('portal.section', 'account'))
        ->assertOk()->assertDontSee('data-modal="header-notifications"', false);
    Livewire::test('notification-sheet')->assertForbidden();
});
