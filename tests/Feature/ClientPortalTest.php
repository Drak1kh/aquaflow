<?php

use App\Actions\PaymentService;
use App\Models\Bill;
use App\Models\ClientNotice;
use App\Models\Connection;
use App\Models\Consumer;
use App\Models\Payment;
use App\Models\ServiceEvent;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('clients have dedicated account connection payment notification settings and help pages', function () {
    $connection = Connection::factory()->create();
    $connection->consumer->waterProvider->update(['phone' => '09123456789', 'email' => 'office@example.com', 'address' => 'Municipal Office']);
    $client = User::factory()->create(['consumer_id' => $connection->consumer_id]);

    $this->actingAs($client)->get(route('dashboard.consumer'))->assertOk()->assertSee('Outstanding balance')->assertSee('Upcoming due date');
    foreach (['account' => 'My Account', 'connection' => 'My Connection', 'payments' => 'Payments', 'notifications' => 'Notifications', 'support' => 'Help'] as $section => $heading) {
        $this->get(route('portal.section', $section))->assertOk()->assertSee($heading);
    }
    $this->get(route('portal.settings'))->assertOk()->assertSee('Contact number');
    $this->get(route('portal.section', 'account'))->assertSee($connection->consumer->account_number)->assertSee($connection->consumer->address);
    $this->get(route('portal.section', 'connection'))->assertSee($connection->number)->assertSee($connection->meter_number);
    $this->get(route('portal.section', 'support'))->assertSee('09123456789')->assertSee('office@example.com')->assertSee('Municipal Office')->assertSee('Frequently asked questions');
});

test('new bills verified payments and service updates create private client notifications', function () {
    $connection = Connection::factory()->create();
    $client = User::factory()->create(['consumer_id' => $connection->consumer_id]);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $connection->water_provider_id]);
    $bill = Bill::factory()->create(['connection_id' => $connection->id]);
    $payment = Payment::factory()->create(['bill_id' => $bill->id, 'consumer_id' => $bill->consumer_id, 'water_provider_id' => $bill->water_provider_id, 'amount' => '40.00', 'status' => 'pending']);

    app(PaymentService::class)->verify($payment, $staff);
    ServiceEvent::factory()->create(['connection_id' => $connection->id, 'action' => 'restore', 'reason' => 'Water service restored']);

    $this->assertDatabaseHas('client_notices', ['user_id' => $client->id, 'consumer_id' => $client->consumer_id, 'title' => 'New water bill']);
    $this->assertDatabaseHas('client_notices', ['user_id' => $client->id, 'title' => 'Payment confirmed']);
    $this->assertDatabaseHas('client_notices', ['user_id' => $client->id, 'title' => 'Service restored']);
    $this->actingAs($client)->get(route('portal.section', 'notifications'))->assertOk()->assertSee('New water bill')->assertSee('Payment confirmed')->assertSee('Water service restored');
});

test('due reminders are idempotent and exclude paid void and distant bills', function () {
    $this->travelTo(now()->setDate(2026, 11, 3));
    $clientConsumer = Consumer::factory()->create();
    $client = User::factory()->create(['consumer_id' => $clientConsumer->id]);
    foreach ([['2026-11-06', 'unpaid', '150.00'], ['2026-10-25', 'unpaid', '150.00'], ['2026-11-06', 'paid', '0.00'], ['2026-11-06', 'void', '150.00'], ['2026-12-15', 'unpaid', '150.00']] as [$due, $status, $total]) {
        $connection = Connection::factory()->create(['consumer_id' => $clientConsumer->id, 'water_provider_id' => $clientConsumer->water_provider_id]);
        Bill::factory()->create(['connection_id' => $connection->id, 'due_at' => $due, 'status' => $status, 'total' => $total]);
    }

    $this->artisan('clients:reminders')->assertSuccessful();
    $this->artisan('clients:reminders')->assertSuccessful();

    expect(ClientNotice::where('user_id', $client->id)->where('title', 'Payment due reminder')->count())->toBe(1);
    expect(ClientNotice::where('user_id', $client->id)->where('title', 'Overdue balance')->count())->toBe(1);
});

test('clients cannot read other inbox entries or payment proofs', function () {
    Storage::fake('local');
    $bill = Bill::factory()->create();
    $client = User::factory()->create(['consumer_id' => $bill->consumer_id]);
    $other = Consumer::factory()->create(['water_provider_id' => $bill->water_provider_id]);
    $otherUser = User::factory()->create(['consumer_id' => $other->id]);
    $notice = ClientNotice::factory()->create(['user_id' => $otherUser->id, 'title' => 'Private notification']);
    Storage::disk('local')->put('payment-proofs/private.pdf', 'private');
    $payment = Payment::factory()->create(['bill_id' => $bill->id, 'consumer_id' => $other->id, 'water_provider_id' => $bill->water_provider_id, 'proof_path' => 'payment-proofs/private.pdf']);

    $this->actingAs($client)->get(route('portal.section', 'notifications'))->assertOk()->assertDontSee('Private notification');
    $this->patch(route('portal.notifications.read', $notice))->assertNotFound();
    $this->get(route('portal.payments.proof', $payment))->assertNotFound();
    expect($notice->fresh()->read_at)->toBeNull();
});

test('a client can mark their own notifications read without changing another account', function () {
    $consumer = Consumer::factory()->create();
    $client = User::factory()->create(['consumer_id' => $consumer->id]);
    $own = ClientNotice::factory()->create(['user_id' => $client->id]);
    $other = ClientNotice::factory()->create();

    $this->actingAs($client)->patch(route('portal.notifications.read', $own))->assertRedirect();
    expect($own->fresh()->read_at)->not->toBeNull();
    expect($other->fresh()->read_at)->toBeNull();
});

test('reassigned logins do not expose a previous consumer inbox', function () {
    $consumer = Consumer::factory()->create();
    $client = User::factory()->create(['consumer_id' => $consumer->id]);
    $notice = ClientNotice::factory()->create(['user_id' => $client->id, 'title' => 'Previous consumer notice']);
    $replacement = Consumer::factory()->create(['water_provider_id' => $consumer->water_provider_id]);
    $client->consumer_id = $replacement->id;
    $client->save();
    $client->unsetRelation('consumer');

    $this->actingAs($client)->get(route('portal.section', 'notifications'))->assertOk()->assertDontSee('Previous consumer notice');
    $this->patch(route('portal.notifications.read', $notice))->assertNotFound();
});

test('support notifications exclude internal administrative notes', function () {
    $consumer = Consumer::factory()->create();
    $client = User::factory()->create(['consumer_id' => $consumer->id]);
    $admin = User::factory()->superAdmin()->create();
    $ticket = SupportTicket::factory()->create(['consumer_id' => $consumer->id, 'water_provider_id' => $consumer->water_provider_id, 'escalated_at' => now()]);

    $this->actingAs($admin)->post(route('admin.tickets.reply', $ticket), ['body' => 'Confidential internal review', 'internal' => 1])->assertSessionHasNoErrors();
    expect(ClientNotice::where('user_id', $client->id)->count())->toBe(0);
    $this->post(route('admin.tickets.reply', $ticket), ['body' => 'We are reviewing your concern'])->assertSessionHasNoErrors();
    $this->actingAs($client)->get(route('portal.section', 'notifications'))->assertOk()->assertSee('Support response')->assertDontSee('Confidential internal review');
});

test('client settings update contact fields and preserve official records', function () {
    $consumer = Consumer::factory()->create(['name' => 'Registered Client', 'account_number' => 'OFFICIAL-001', 'address' => 'Official service address']);
    $client = User::factory()->create(['consumer_id' => $consumer->id]);
    $this->actingAs($client);

    Livewire::test('pages::settings.profile')->set('name', 'Forged Name')->set('email', 'updated-client@example.com')->set('phone', '09987654321')->call('updateProfileInformation')->assertHasNoErrors();

    expect($consumer->fresh()->name)->toBe('Registered Client');
    expect($consumer->fresh()->account_number)->toBe('OFFICIAL-001');
    expect($consumer->fresh()->address)->toBe('Official service address');
    expect($consumer->fresh()->phone)->toBe('09987654321');
    expect($client->fresh()->email)->toBe('updated-client@example.com');
    expect($client->fresh()->email_verified_at)->toBeNull();
    $this->get(route('portal.settings'))->assertOk();
});
