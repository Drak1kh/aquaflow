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

test('provider queue searches remain scoped to the provider and status', function (string $field) {
    $consumer = Consumer::factory()->create(['name' => 'Ada Rivera', 'account_number' => 'QUEUE-417']);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $consumer->water_provider_id]);
    $ticket = SupportTicket::factory()->create(['consumer_id' => $consumer->id, 'subject' => 'Meter investigation']);
    SupportTicket::factory()->create(['consumer_id' => $consumer->id, 'subject' => $ticket->subject, 'status' => 'closed']);
    $foreignConsumer = Consumer::factory()->create(['name' => $consumer->name, 'account_number' => $consumer->account_number]);
    SupportTicket::factory()->create(['consumer_id' => $foreignConsumer->id, 'subject' => $ticket->subject]);
    $search = match ($field) {
        'subject' => 'investigation', 'name' => 'Rivera', 'account' => 'QUEUE-417', 'id' => '#'.$ticket->id,
    };

    $this->actingAs($staff)->get(route('provider.tickets.index', ['search' => $search, 'status' => 'open']))
        ->assertOk()->assertViewHas('tickets', fn ($tickets) => $tickets->pluck('id')->all() === [$ticket->id]);
})->with(['subject', 'name', 'account', 'id']);

test('provider queue validates filters before querying', function (array $query, string $error) {
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => WaterProvider::factory()]);

    $this->actingAs($staff)->getJson(route('provider.tickets.index', $query))
        ->assertUnprocessable()->assertJsonValidationErrors($error);
})->with([
    'unknown status' => [['status' => 'invalid'], 'status'],
    'array search' => [['search' => ['invalid']], 'search'],
    'long search' => [['search' => str_repeat('a', 256)], 'search'],
    'array selection' => [['ticket' => [1]], 'ticket'],
]);

test('provider selection defaults to the first filtered ticket and rejects inaccessible selections', function () {
    $consumer = Consumer::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $consumer->water_provider_id]);
    $first = SupportTicket::factory()->create(['consumer_id' => $consumer->id]);
    $latest = SupportTicket::factory()->create(['consumer_id' => $consumer->id]);
    $foreign = SupportTicket::factory()->create();
    $this->actingAs($staff);

    $this->get(route('provider.tickets.index'))->assertOk()
        ->assertViewHas('selectedTicket', fn ($ticket) => $ticket?->is($latest));
    $this->get(route('provider.tickets.index', ['ticket' => $first->id]))->assertOk()
        ->assertViewHas('selectedTicket', fn ($ticket) => $ticket?->is($first));
    $this->get(route('provider.tickets.index', ['ticket' => $foreign->id]))->assertNotFound();
    $this->get(route('provider.tickets.index', ['ticket' => $first->id, 'status' => 'closed']))->assertNotFound();
    $this->get(route('provider.tickets.index', ['status' => 'closed']))->assertOk()
        ->assertViewHas('selectedTicket', null);
});

test('provider queue pagination retains filters and resets selection for the next page', function () {
    $consumer = Consumer::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $consumer->water_provider_id]);
    $tickets = SupportTicket::factory()->count(21)->create(['consumer_id' => $consumer->id, 'subject' => 'Meter query']);
    $query = ['search' => 'Meter', 'status' => 'open', 'ticket' => $tickets->first()->id];

    $this->actingAs($staff)->get(route('provider.tickets.index', $query))->assertOk()
        ->assertViewHas('tickets', function ($tickets) use ($query) {
            parse_str(parse_url($tickets->nextPageUrl(), PHP_URL_QUERY), $next);

            return $tickets->count() === 20 && $tickets->total() === 21
                && $next === ['search' => $query['search'], 'status' => $query['status'], 'page' => '2'];
        });
});

test('consumer support history includes public conversations and reply forms only for open tickets', function () {
    $consumer = Consumer::factory()->create();
    $user = User::factory()->create(['consumer_id' => $consumer->id]);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $consumer->water_provider_id, 'name' => 'Morgan Utility']);
    $ticket = SupportTicket::factory()->create(['consumer_id' => $consumer->id, 'description' => 'Please investigate the meter reading.']);
    $ticket->messages()->create(['author_id' => $staff->id, 'body' => 'The meter inspection is scheduled.', 'internal' => false]);
    $ticket->messages()->create(['author_id' => $staff->id, 'body' => 'Confidential staff discussion', 'internal' => true]);
    $closed = SupportTicket::factory()->create(['consumer_id' => $consumer->id, 'status' => 'closed']);
    SupportTicket::factory()->create(['description' => 'Another account private concern']);

    $this->actingAs($user)->get(route('portal.section', 'support'))->assertOk()
        ->assertSee($ticket->description)->assertSee('The meter inspection is scheduled.')->assertSee($staff->name)
        ->assertDontSee('Confidential staff discussion')->assertDontSee('Another account private concern')
        ->assertSee('action="'.route('portal.tickets.reply', $ticket).'"', false)
        ->assertSee('enctype="multipart/form-data"', false)->assertSee('name="body"', false)->assertSee('name="attachment"', false)
        ->assertDontSee('action="'.route('portal.tickets.reply', $closed).'"', false);
});

test('escalating a filtered queue ticket keeps its conversation accessible', function () {
    Notification::fake();
    $consumer = Consumer::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $consumer->water_provider_id]);
    $ticket = SupportTicket::factory()->create(['consumer_id' => $consumer->id, 'status' => 'open']);
    $queue = route('provider.tickets.index', ['status' => 'open', 'ticket' => $ticket->id]);

    $this->actingAs($staff)->get($queue)->assertOk();
    $response = $this->from($queue)->post(route('provider.tickets.escalate', $ticket))
        ->assertRedirect()->assertSessionHasNoErrors();

    $this->get($response->headers->get('Location'))->assertOk()
        ->assertViewHas('selectedTicket', fn ($selected) => $selected?->is($ticket) && $selected->status === 'under_review');
});

test('consumer reply validation marks only the submitted ticket composer invalid', function () {
    $consumer = Consumer::factory()->create();
    $user = User::factory()->create(['consumer_id' => $consumer->id]);
    [$other, $submitted] = SupportTicket::factory()->count(2)->create(['consumer_id' => $consumer->id])->all();
    $support = route('portal.section', 'support');

    $this->actingAs($user)->from($support)->post(route('portal.tickets.reply', $submitted), [
        '_form' => 'reply-to-ticket-'.$submitted->id, 'body' => '',
    ])->assertRedirect($support);
    $response = $this->get($support)->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $submittedForm = '//form[@action="'.route('portal.tickets.reply', $submitted).'"]';
    $otherForm = '//form[@action="'.route('portal.tickets.reply', $other).'"]';

    expect($xpath->query($submittedForm.'//textarea[@name="body" and @aria-invalid="true"]')->length)->toBe(1);
    expect($xpath->query($submittedForm)->item(0)->textContent)->toContain('The body field is required.');
    expect($xpath->query($otherForm.'//textarea[@name="body"]')->length)->toBe(1);
    expect($xpath->query($otherForm.'//textarea[@name="body" and @aria-invalid="true"]')->length)->toBe(0);
});
