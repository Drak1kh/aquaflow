<?php

use App\Models\Consumer;
use App\Models\ProviderMembership;
use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Models\WaterProvider;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('provider can escalate a ticket while internal notes and files stay private', function () {
    Storage::fake('local');
    $this->seed(AuthorizationSeeder::class);
    $provider = WaterProvider::factory()->create();
    $consumerUser = User::factory()->create();
    $consumer = Consumer::factory()->create(['water_provider_id' => $provider->id, 'user_id' => $consumerUser->id]);
    $staff = User::factory()->create();
    ProviderMembership::factory()->create(['water_provider_id' => $provider->id, 'user_id' => $staff->id, 'role_id' => Role::where('slug', 'provider_manager')->firstOrFail()->id]);
    $admin = User::factory()->create(['is_super_admin' => true]);

    $this->actingAs($consumerUser)->post(route('consumer.support.store'), [
        'type' => 'billing', 'subject' => 'Incorrect reading', 'body' => 'Please review the reading.',
    ])->assertSessionHasNoErrors();
    $ticket = SupportTicket::where('consumer_id', $consumer->id)->firstOrFail();

    $otherProvider = WaterProvider::factory()->create();
    $otherStaff = User::factory()->create();
    ProviderMembership::factory()->create(['water_provider_id' => $otherProvider->id, 'user_id' => $otherStaff->id, 'role_id' => Role::where('slug', 'provider_manager')->firstOrFail()->id]);
    $this->actingAs($otherStaff)->withSession(['active_provider_id' => $otherProvider->id])
        ->post(route('provider.support.reply', $ticket), ['body' => 'Cross-provider reply'])->assertNotFound();

    $this->actingAs($staff)->withSession(['active_provider_id' => $provider->id])
        ->post(route('provider.support.reply', $ticket), [
            'body' => 'Staff-only investigation', 'internal' => 1,
            'attachment' => UploadedFile::fake()->create('internal.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors();
    $attachment = TicketAttachment::where('support_ticket_id', $ticket->id)->firstOrFail();
    $this->actingAs($consumerUser)->get(route('consumer.support'))->assertOk()->assertDontSee('Staff-only investigation');
    $this->get(route('support.attachments.show', $attachment))->assertNotFound();

    $this->actingAs($staff)->withSession(['active_provider_id' => $provider->id])
        ->post(route('provider.support.escalate', $ticket))->assertSessionHasNoErrors();
    $this->actingAs($admin)->get(route('admin.support'))->assertOk()->assertSee('Incorrect reading');
    $this->post(route('admin.support.reply', $ticket), ['body' => 'We are reviewing this.'])->assertSessionHasNoErrors();
    $this->actingAs($consumerUser)->get(route('consumer.support'))->assertOk()->assertSee('We are reviewing this.');
});

test('platform oversight is reserved for super admins', function () {
    $staff = User::factory()->create();
    $admin = User::factory()->create(['is_super_admin' => true]);

    $this->actingAs($staff)->get(route('admin.overview'))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.overview'))->assertOk();
});
