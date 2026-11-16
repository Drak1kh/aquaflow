<?php

use App\Models\ClientNotice;
use App\Models\Consumer;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\WaterProvider;
use App\UserRole;
use Livewire\Livewire;

test('every role uses the shared notification panel without exposing another inbox', function (UserRole $role, string $routeName, string $emptyMessage) {
    $user = User::factory()->create([
        'role' => $role,
        'consumer_id' => $role === UserRole::Consumer ? Consumer::factory() : null,
        'water_provider_id' => $role === UserRole::WaterProvider ? WaterProvider::factory() : null,
    ]);
    ClientNotice::factory()->create(['title' => 'Private consumer notification']);

    $response = $this->actingAs($user)->get(route($routeName))
        ->assertOk()->assertSee('id="header-notifications"', false)
        ->assertSee('role="dialog"', false)->assertSee('popover="manual"', false)
        ->assertSee($emptyMessage)->assertDontSee('Private consumer notification');

    if ($role !== UserRole::Consumer) {
        $response->assertDontSee('wire:click="toggleUnread"', false)
            ->assertDontSee('wire:click="markAllAsRead"', false);
    }
})->with([
    'super admin' => [UserRole::SuperAdmin, 'dashboard.admin', 'No notifications yet'],
    'water provider' => [UserRole::WaterProvider, 'dashboard.provider', 'No notifications yet'],
    'consumer' => [UserRole::Consumer, 'dashboard.consumer', 'No notifications to show.'],
]);

test('the header contains an accessible notification panel with only the current consumer inbox', function () {
    $consumer = Consumer::factory()->create();
    $client = User::factory()->create(['consumer_id' => $consumer->id]);
    ClientNotice::factory()->create(['user_id' => $client->id, 'title' => 'Your latest bill']);
    ClientNotice::factory()->create(['title' => 'Another account notice']);
    ClientNotice::factory()->create(['user_id' => $client->id, 'consumer_id' => Consumer::factory(), 'title' => 'Previous consumer notice']);

    $this->actingAs($client)->get(route('portal.section', 'account'))
        ->assertOk()->assertSee('id="header-notifications"', false)
        ->assertSee('role="dialog"', false)->assertSee('popover="manual"', false)
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

test('notification document links use normal navigation so print styles cannot leak into the portal', function (string $url) {
    $client = User::factory()->create(['consumer_id' => Consumer::factory()]);
    ClientNotice::factory()->create(['user_id' => $client->id, 'url' => $url]);

    $html = Livewire::actingAs($client)->test('notification-sheet')->html();
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $link = (new DOMXPath($document))->query('//a[@href="'.$url.'"]')->item(0);

    expect($link)->not->toBeNull();
    expect($link->textContent)->toContain('View details');
    expect($link->hasAttribute('wire:navigate'))->toBeFalse();
})->with(['/documents/bills/123', '/documents/payments/123']);

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
        ->assertOk()->assertDontSee('id="header-notifications"', false);
    Livewire::test('notification-sheet')->assertForbidden();
});
