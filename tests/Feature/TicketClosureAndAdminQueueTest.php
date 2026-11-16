<?php

use App\Models\AuditLog;
use App\Models\Consumer;
use App\Models\RolePermission;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->consumer = Consumer::factory()->create();
    $this->ticket = SupportTicket::factory()->create(['consumer_id' => $this->consumer->id]);
    $this->providerUser = User::factory()->waterProvider()->create(['water_provider_id' => $this->consumer->water_provider_id]);
    $this->consumerUser = User::factory()->create(['consumer_id' => $this->consumer->id]);
    $this->adminUser = User::factory()->superAdmin()->create();
});

test('internal ticket attachments are downloadable only by administrators', function () {
    Storage::fake('local');
    $this->ticket->update(['escalated_at' => now()]);
    Storage::disk('local')->put('ticket-attachments/public.pdf', 'Public attachment');
    Storage::disk('local')->put('ticket-attachments/internal.pdf', 'Confidential attachment');
    $public = $this->ticket->messages()->create([
        'author_id' => $this->adminUser->id, 'body' => 'Public response', 'internal' => false,
        'attachment_path' => 'ticket-attachments/public.pdf', 'attachment_name' => 'public.pdf',
    ]);
    $internal = $this->ticket->messages()->create([
        'author_id' => $this->adminUser->id, 'body' => 'Private assessment', 'internal' => true,
        'attachment_path' => 'ticket-attachments/internal.pdf', 'attachment_name' => 'internal.pdf',
    ]);

    $this->actingAs($this->adminUser)->get(route('admin.tickets.attachment', $internal))
        ->assertOk()->assertDownload('internal.pdf');
    $this->actingAs($this->consumerUser)->get(route('portal.tickets.attachment', $internal))->assertNotFound();
    $this->actingAs($this->providerUser)->get(route('provider.tickets.attachment', $public))
        ->assertOk()->assertDownload('public.pdf');
    $this->get(route('provider.tickets.attachment', $internal))->assertNotFound();
});

test('ticket close succeeds once and preserves the original resolution on repeat', function (string $role, bool $resolved) {
    $timestamp = $resolved ? now()->subDay()->startOfSecond() : null;
    $this->ticket->update(['status' => $resolved ? 'resolved' : 'open', 'resolved_at' => $timestamp]);
    $prefix = $role === 'provider' ? 'provider' : 'portal';
    $user = $role === 'provider' ? $this->providerUser : $this->consumerUser;
    $this->actingAs($user);
    $response = $this->from(route('provider.tickets.index', ['status' => 'open', 'ticket' => $this->ticket->id]))
        ->post(route($prefix.'.tickets.close', $this->ticket), ['resolution' => 'Concern addressed.'])
        ->assertRedirect()->assertSessionHasNoErrors();
    $this->get($response->headers->get('Location'))->assertOk()
        ->assertDontSee('action="'.route($prefix.'.tickets.reply', $this->ticket).'"', false)
        ->assertDontSee('action="'.route($prefix.'.tickets.close', $this->ticket).'"', false)
        ->assertDontSee('action="'.route('provider.tickets.escalate', $this->ticket).'"', false);
    $closed = $this->ticket->fresh();
    expect($closed->status)->toBe('closed')->and($closed->resolution)->toBe('Concern addressed.')
        ->and($closed->resolved_at)->not->toBeNull();
    if ($resolved) {
        expect($closed->resolved_at->equalTo($timestamp))->toBeTrue();
    }
    $this->travel(1)->hour();
    $this->post(route($prefix.'.tickets.close', $this->ticket), ['resolution' => 'Replacement reason'])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($this->ticket->fresh()->only(['status', 'resolution', 'resolved_at', 'updated_at']))
        ->toEqual($closed->only(['status', 'resolution', 'resolved_at', 'updated_at']));
    expect(AuditLog::where('subject_type', $this->ticket->getMorphClass())->where('subject_id', $this->ticket->id)
        ->where('action', 'ticket.updated')->count())->toBe(1);
})->with([['provider', false], ['portal', false], ['provider', true], ['portal', true]]);

test('ticket close enforces ownership and response permission', function (string $role, bool $denied) {
    $user = $role === 'provider' ? $this->providerUser : $this->consumerUser;
    if ($denied) {
        RolePermission::create(['role' => $user->role->value, 'permission' => 'tickets.respond', 'allowed' => false]);
        $ticket = $this->ticket;
    } else {
        $consumer = Consumer::factory()->create($role === 'portal' ? ['water_provider_id' => $this->consumer->water_provider_id] : []);
        $ticket = SupportTicket::factory()->create(['consumer_id' => $consumer->id]);
    }
    $this->actingAs($user)->post(route($role.'.tickets.close', $ticket), ['resolution' => 'Close concern'])
        ->assertStatus($denied ? 403 : 404);
    expect($ticket->fresh()->status)->toBe('open');
})->with([['provider', false], ['portal', false], ['provider', true], ['portal', true]]);

test('ticket close validates its reason', function (string $role, ?string $reason) {
    $this->actingAs($role === 'provider' ? $this->providerUser : $this->consumerUser)
        ->postJson(route($role.'.tickets.close', $this->ticket), ['resolution' => $reason])
        ->assertUnprocessable()->assertJsonValidationErrors('resolution');
    expect($this->ticket->fresh()->status)->toBe('open');
})->with([['provider', null], ['portal', null], ['provider', str_repeat('x', 5001)], ['portal', str_repeat('x', 5001)]]);

test('closed tickets reject replies and escalation without mutation', function (string $endpoint) {
    $this->ticket->update(['status' => 'closed', 'resolution' => 'Finished', 'resolved_at' => now(), 'escalated_at' => $endpoint === 'admin.tickets.reply' ? now() : null]);
    $before = $this->ticket->fresh()->getAttributes();
    $user = match (explode('.', $endpoint)[0]) {
        'provider' => $this->providerUser, 'portal' => $this->consumerUser, 'admin' => $this->adminUser,
    };
    $this->actingAs($user)->post(route($endpoint, $this->ticket), ['body' => 'Another reply'])->assertUnprocessable();
    expect($this->ticket->fresh()->getAttributes())->toBe($before);
    expect($this->ticket->messages()->count())->toBe(0);
})->with(['provider.tickets.reply', 'portal.tickets.reply', 'admin.tickets.reply', 'provider.tickets.escalate']);

test('admin queue searches escalated tickets and renders both message visibilities', function (string $field) {
    $this->consumer->update(['name' => 'Rivera Search', 'account_number' => 'ADMIN-417']);
    $this->consumer->waterProvider->update(['name' => 'Search Utility']);
    $this->ticket->update(['subject' => 'Investigation queue', 'escalated_at' => now(), 'type' => 'billing']);
    SupportTicket::factory()->create(['subject' => 'Unrelated concern', 'escalated_at' => now(), 'type' => 'billing']);
    SupportTicket::factory()->create(['subject' => 'Investigation queue', 'escalated_at' => now(), 'type' => 'billing', 'status' => 'closed']);
    SupportTicket::factory()->create(['consumer_id' => $this->consumer->id, 'subject' => 'Investigation queue', 'type' => 'billing']);
    $this->ticket->messages()->create(['author_id' => $this->adminUser->id, 'body' => 'Public queue response', 'internal' => false]);
    $this->ticket->messages()->create(['author_id' => $this->adminUser->id, 'body' => 'Private queue assessment', 'internal' => true]);
    $search = match ($field) {
        'id' => '#'.$this->ticket->id, 'subject' => 'Investigation', 'customer' => 'Rivera', 'account' => 'ADMIN-417', 'provider' => 'Search Utility',
    };
    $this->actingAs($this->adminUser)->get(route('admin.tickets.index', ['search' => $search, 'status' => 'open', 'type' => 'billing']))
        ->assertOk()->assertViewHas('tickets', fn ($tickets) => $tickets->pluck('id')->all() === [$this->ticket->id])
        ->assertViewHas('selectedTicket', fn ($ticket) => $ticket?->is($this->ticket))->assertViewHas('admins')
        ->assertSee('Public queue response')->assertSee('Private queue assessment');
})->with(['id', 'subject', 'customer', 'account', 'provider']);

test('admin queue selection and permalink retain escalated scope', function () {
    $this->actingAs($this->adminUser);
    $this->get(route('admin.tickets.index'))->assertOk()->assertViewHas('selectedTicket', null);
    $this->get(route('admin.tickets.index', ['ticket' => $this->ticket->id]))->assertNotFound();
    $this->ticket->update(['escalated_at' => now()]);
    $latest = SupportTicket::factory()->create(['escalated_at' => now()->addMinute()]);
    $queue = $this->get(route('admin.tickets.index'))->assertOk()
        ->assertViewHas('selectedTicket', fn ($ticket) => $ticket?->is($latest));
    $this->get(route('admin.tickets.show', $this->ticket))->assertOk()->assertViewIs($queue->original->name())
        ->assertViewHas('selectedTicket', fn ($ticket) => $ticket?->is($this->ticket));
    $this->get(route('admin.tickets.index', ['ticket' => $this->ticket->id, 'status' => 'closed']))->assertNotFound();
});

test('admin queue pagination retains filters and resets selection', function () {
    $tickets = SupportTicket::factory()->count(21)->create(['subject' => 'Page test', 'type' => 'billing', 'escalated_at' => now()]);
    $this->actingAs($this->adminUser)->get(route('admin.tickets.index', ['search' => 'Page', 'status' => 'open', 'type' => 'billing', 'ticket' => $tickets->first()->id]))
        ->assertOk()->assertViewHas('tickets', function ($tickets) {
            parse_str(parse_url($tickets->nextPageUrl(), PHP_URL_QUERY), $next);

            return $tickets->count() === 20 && $tickets->total() === 21
                && $next === ['search' => 'Page', 'status' => 'open', 'type' => 'billing', 'page' => '2'];
        });

    $permalink = $this->get(route('admin.tickets.show', [
        'ticket' => $tickets->last()->id, 'search' => 'Page', 'status' => 'open', 'type' => 'billing',
    ]))->assertOk();
    $nextUrl = $permalink->viewData('tickets')->nextPageUrl();
    expect(parse_url($nextUrl, PHP_URL_PATH))->toBe(parse_url(route('admin.tickets.index'), PHP_URL_PATH));
    parse_str(parse_url($nextUrl, PHP_URL_QUERY), $nextQuery);
    expect($nextQuery)->toBe(['search' => 'Page', 'status' => 'open', 'type' => 'billing', 'page' => '2']);
    $nextPage = $this->get($nextUrl)->assertOk();
    expect($nextPage->viewData('selectedTicket')->id)->toBe($nextPage->viewData('tickets')->first()->id)
        ->toBe($tickets->first()->id);
});

test('admin queue validates filter inputs', function (array $query, string $field) {
    $this->actingAs($this->adminUser)->getJson(route('admin.tickets.index', $query))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    [['search' => ['invalid']], 'search'], [['status' => 'invalid'], 'status'],
    [['type' => 'invalid'], 'type'], [['ticket' => [1]], 'ticket'],
]);
