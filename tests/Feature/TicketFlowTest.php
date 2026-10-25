<?php

use App\Models\Consumer;
use App\Models\SupportTicket;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WaterProvider;
use App\Notifications\EscalatedTicketNotification;
use Illuminate\Support\Facades\Notification;

test('a consumer ticket can be escalated and reviewed without exposing internal notes', function () {
    $provider = WaterProvider::create(['name' => 'North Water', 'code' => 'north']);
    $consumer = Consumer::create(['water_provider_id' => $provider->id, 'account_number' => 'A-001', 'name' => 'Ada Rivera', 'address' => 'North Street']);
    $portalUser = User::factory()->create(['consumer_id' => $consumer->id]);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $provider->id]);
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($portalUser)->post(route('portal.tickets.store'), [
        'type' => 'billing', 'subject' => 'Unexpected charge', 'description' => 'Please check my January bill.',
    ])->assertSessionHasNoErrors();
    $ticket = SupportTicket::where('subject', 'Unexpected charge')->firstOrFail();

    $this->actingAs($staff)->post(route('provider.tickets.escalate', $ticket))->assertSessionHasNoErrors();
    expect($ticket->fresh()->escalated_at)->not->toBeNull();

    $this->actingAs($admin)->post(route('admin.tickets.reply', $ticket), [
        'body' => 'Internal review only', 'internal' => true,
    ])->assertSessionHasNoErrors();
    $this->post(route('admin.tickets.reply', $ticket), [
        'body' => 'We are reviewing your concern.',
    ])->assertSessionHasNoErrors();
    $this->put(route('admin.tickets.update', $ticket), [
        'status' => 'resolved', 'resolution' => 'Incorrect charge corrected.',
    ])->assertSessionHasNoErrors();

    $this->actingAs($portalUser)->get(route('portal.tickets.show', $ticket))
        ->assertOk()->assertSee('We are reviewing your concern.')->assertDontSee('Internal review only');
    expect($ticket->fresh()->status)->toBe('resolved');
});

test('consumers cannot read another consumer ticket', function () {
    $provider = WaterProvider::create(['name' => 'North Water', 'code' => 'north']);
    $owner = Consumer::create(['water_provider_id' => $provider->id, 'account_number' => 'A-001', 'name' => 'Ada', 'address' => 'North Street']);
    $other = Consumer::create(['water_provider_id' => $provider->id, 'account_number' => 'A-002', 'name' => 'Bob', 'address' => 'North Street']);
    $ticket = SupportTicket::create(['water_provider_id' => $provider->id, 'consumer_id' => $owner->id, 'type' => 'account', 'subject' => 'Private', 'description' => 'Private concern']);
    $otherUser = User::factory()->create(['consumer_id' => $other->id]);

    $this->actingAs($otherUser)->get(route('portal.tickets.show', $ticket))->assertNotFound();
});

test('enabled email alerts notify active super admins about an escalation', function () {
    Notification::fake();
    SystemSetting::create(['key' => 'email_notifications', 'value' => '1']);
    $provider = WaterProvider::create(['name' => 'North Water', 'code' => 'north']);
    $consumer = Consumer::create(['water_provider_id' => $provider->id, 'account_number' => 'A-001', 'name' => 'Ada', 'address' => 'North Street']);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $provider->id]);
    $admin = User::factory()->superAdmin()->create();
    $inactiveAdmin = User::factory()->superAdmin()->create(['is_active' => false]);
    $ticket = SupportTicket::create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'type' => 'billing', 'subject' => 'Bill review', 'description' => 'Please review.']);

    $this->actingAs($staff)->post(route('provider.tickets.escalate', $ticket))->assertSessionHasNoErrors();

    Notification::assertSentTo($admin, EscalatedTicketNotification::class);
    Notification::assertNotSentTo($inactiveAdmin, EscalatedTicketNotification::class);
});

test('an inactive consumer record cannot access the portal', function () {
    $provider = WaterProvider::create(['name' => 'North Water', 'code' => 'north']);
    $consumer = Consumer::create(['water_provider_id' => $provider->id, 'account_number' => 'A-001', 'name' => 'Ada', 'address' => 'North Street', 'is_active' => false]);
    $portalUser = User::factory()->create(['consumer_id' => $consumer->id]);

    $this->actingAs($portalUser)->get(route('dashboard.consumer'))->assertRedirect(route('login'));
});
