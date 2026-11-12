<?php

use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\BillAdjustment;
use App\Models\ClientNotice;
use App\Models\Connection;
use App\Models\Consumer;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\RolePermission;
use App\Models\ServiceEvent;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\WaterProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

test('blank registration identifiers are compact sequential and provider scoped', function () {
    foreach (WaterProvider::factory()->count(2)->create() as $provider) {
        $staff = User::factory()->waterProvider()->create(['water_provider_id' => $provider->id]);
        foreach ([1, 2] as $sequence) {
            $this->actingAs($staff)->post(route('provider.consumers.store'), [
                'name' => 'Consumer '.$sequence, 'address' => 'North Street',
                ...($sequence === 1 ? ['account_number' => '', 'number' => '', 'meter_number' => ''] : []),
            ])->assertRedirect()->assertSessionHasNoErrors();

            $consumer = $provider->consumers()->latest('id')->firstOrFail();
            $connection = $consumer->connections()->sole();
            expect($consumer->account_number)->toBe('ACC-00'.$sequence)
                ->and($connection->number)->toBe('CN-00'.$sequence)
                ->and($connection->meter_number)->toBe('MTR-00'.$sequence);
        }
    }
});

test('blank edit identifiers are generated while omitted legacy identifiers stay unchanged', function () {
    $connection = Connection::factory()->create(['number' => 'CON-LEGACY', 'meter_number' => 'MTR-LEGACY']);
    $consumer = $connection->consumer;
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $consumer->water_provider_id]);
    $this->actingAs($staff)->put(route('provider.consumers.update', $consumer), [
        'name' => 'Updated name', 'address' => 'North Street',
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($consumer->fresh()->account_number)->toBe($consumer->account_number)
        ->and($connection->fresh()->number)->toBe('CON-LEGACY')
        ->and($connection->fresh()->meter_number)->toBe('MTR-LEGACY');

    $this->put(route('provider.consumers.update', $consumer), [
        'name' => 'Updated name', 'address' => 'North Street', 'connection_id' => $connection->id,
        'account_number' => '', 'number' => '', 'meter_number' => '',
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($consumer->fresh()->account_number)->toBe('ACC-001')
        ->and($connection->fresh()->number)->toBe('CN-001')
        ->and($connection->fresh()->meter_number)->toBe('MTR-001');
});

test('explicit identifiers are preserved and automatic identifiers skip collisions', function () {
    $provider = WaterProvider::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $provider->id]);
    $this->actingAs($staff)->post(route('provider.consumers.store'), [
        'name' => 'Explicit consumer', 'address' => 'North Street',
        'account_number' => 'ACC-001', 'number' => 'CN-001', 'meter_number' => 'MTR-001',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $explicit = $provider->consumers()->sole();
    expect($explicit->account_number)->toBe('ACC-001')
        ->and($explicit->connections()->sole()->number)->toBe('CN-001')
        ->and($explicit->connections()->sole()->meter_number)->toBe('MTR-001');

    $this->post(route('provider.consumers.store'), ['name' => 'Automatic consumer', 'address' => 'North Street'])
        ->assertRedirect()->assertSessionHasNoErrors();
    $automatic = $provider->consumers()->latest('id')->firstOrFail();
    expect($automatic->account_number)->toBe('ACC-002')
        ->and($automatic->connections()->sole()->number)->toBe('CN-002')
        ->and($automatic->connections()->sole()->meter_number)->toBe('MTR-002');
});

test('generated identifiers are never recycled after their consumer is removed', function () {
    $provider = WaterProvider::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $provider->id]);
    $this->actingAs($staff)->post(route('provider.consumers.store'), ['name' => 'First', 'address' => 'North Street'])
        ->assertRedirect()->assertSessionHasNoErrors();
    $provider->consumers()->sole()->delete();
    $this->post(route('provider.consumers.store'), ['name' => 'Second', 'address' => 'North Street'])
        ->assertRedirect()->assertSessionHasNoErrors();
    $consumer = $provider->consumers()->sole();
    expect($consumer->account_number)->toBe('ACC-002')
        ->and($consumer->connections()->sole()->number)->toBe('CN-002')
        ->and($consumer->connections()->sole()->meter_number)->toBe('MTR-002');
});

test('identifier migration remembers existing compact numbers even after deletion', function () {
    $consumer = Consumer::factory()->create(['account_number' => 'ACC-1000']);
    Connection::factory()->create(['consumer_id' => $consumer->id, 'water_provider_id' => $consumer->water_provider_id, 'number' => 'CN-042', 'meter_number' => 'MTR-123']);
    $migrationPath = database_path('migrations/2026_11_11_000001_add_identifier_sequences_to_water_providers_table.php');
    expect($migrationPath)->toBeFile();
    $migration = require $migrationPath;
    $migration->down();
    $migration->up();
    $consumer->delete();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $consumer->water_provider_id]);
    $this->actingAs($staff)->post(route('provider.consumers.store'), ['name' => 'Next', 'address' => 'North Street'])
        ->assertRedirect()->assertSessionHasNoErrors();
    $next = Consumer::where('water_provider_id', $consumer->water_provider_id)->sole();
    expect($next->account_number)->toBe('ACC-1001')
        ->and($next->connections()->sole()->number)->toBe('CN-043')
        ->and($next->connections()->sole()->meter_number)->toBe('MTR-124');
});

test('delete removes the selected consumer portal and dependents but retains audit and files', function () {
    Storage::fake('local');
    Storage::disk('local')->put('payment-proofs/retained.pdf', 'proof');
    Storage::disk('local')->put('ticket-attachments/retained.pdf', 'attachment');
    $bill = Bill::factory()->create();
    $consumer = $bill->consumer;
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $consumer->water_provider_id]);
    $portal = User::factory()->create(['consumer_id' => $consumer->id]);
    $other = Consumer::factory()->create(['water_provider_id' => $consumer->water_provider_id]);
    $otherConnection = Connection::factory()->create(['consumer_id' => $other->id, 'water_provider_id' => $other->water_provider_id]);
    $otherPortal = User::factory()->create(['consumer_id' => $other->id]);
    $otherNotice = ClientNotice::factory()->create(['consumer_id' => $other->id, 'user_id' => $otherPortal->id]);
    $payment = Payment::factory()->create(['bill_id' => $bill->id, 'proof_path' => 'payment-proofs/retained.pdf']);
    $allocation = PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'bill_id' => $bill->id]);
    $adjustment = BillAdjustment::factory()->create(['bill_id' => $bill->id]);
    $event = ServiceEvent::factory()->create(['connection_id' => $bill->connection_id]);
    $ticket = SupportTicket::factory()->create(['consumer_id' => $consumer->id, 'water_provider_id' => $consumer->water_provider_id, 'opened_by' => $portal->id]);
    $message = TicketMessage::factory()->create(['support_ticket_id' => $ticket->id, 'author_id' => $portal->id, 'attachment_path' => 'ticket-attachments/retained.pdf']);
    $oldAudit = AuditLog::factory()->create(['subject_type' => Consumer::class, 'subject_id' => $consumer->id]);
    DB::table('sessions')->insert(['id' => 'portal-session', 'user_id' => $portal->id, 'payload' => '', 'last_activity' => time()]);
    DB::table('password_reset_tokens')->insert(['email' => $portal->email, 'token' => 'reset-token']);

    $this->actingAs($staff)->delete('/provider/consumers/'.$consumer->id, ['account_number' => $consumer->account_number])
        ->assertRedirect(route('provider.consumers.index'))->assertSessionHasNoErrors();

    foreach (['consumers' => $consumer->id, 'users' => $portal->id, 'connections' => $bill->connection_id, 'meter_readings' => $bill->meter_reading_id, 'bills' => $bill->id, 'payments' => $payment->id, 'payment_allocations' => $allocation->id, 'bill_adjustments' => $adjustment->id, 'service_events' => $event->id, 'support_tickets' => $ticket->id, 'ticket_messages' => $message->id] as $table => $id) {
        $this->assertDatabaseMissing($table, ['id' => $id]);
    }
    $this->assertDatabaseMissing('client_notices', ['consumer_id' => $consumer->id]);
    $this->assertDatabaseMissing('sessions', ['user_id' => $portal->id]);
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $portal->email]);
    $this->assertDatabaseHas('consumers', ['id' => $other->id]);
    $this->assertDatabaseHas('connections', ['id' => $otherConnection->id]);
    $this->assertDatabaseHas('users', ['id' => $otherPortal->id]);
    $this->assertDatabaseHas('client_notices', ['id' => $otherNotice->id]);
    $this->assertDatabaseHas('audit_logs', ['id' => $oldAudit->id]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'consumer.deleted', 'subject_type' => Consumer::class, 'subject_id' => $consumer->id, 'actor_id' => $staff->id]);
    Storage::disk('local')->assertExists(['payment-proofs/retained.pdf', 'ticket-attachments/retained.pdf']);
});

test('delete requires the exact account number', function (string $confirmation) {
    $consumer = Consumer::factory()->create(['account_number' => 'ACC-001']);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $consumer->water_provider_id]);
    $this->actingAs($staff)->delete('/provider/consumers/'.$consumer->id, ['account_number' => $confirmation])
        ->assertSessionHasErrors('account_number');
    $this->assertDatabaseHas('consumers', ['id' => $consumer->id]);
})->with(['', 'ACC-002', 'acc-001', ' ACC-001 ']);

test('delete is provider scoped and permission protected', function () {
    $consumer = Consumer::factory()->create();
    $otherStaff = User::factory()->waterProvider()->create(['water_provider_id' => WaterProvider::factory()->create()->id]);
    $this->actingAs($otherStaff)->delete('/provider/consumers/'.$consumer->id, ['account_number' => $consumer->account_number])->assertNotFound();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $consumer->water_provider_id]);
    RolePermission::create(['role' => 'water_provider', 'permission' => 'consumers.manage', 'allowed' => false]);
    $this->actingAs($staff)->delete('/provider/consumers/'.$consumer->id, ['account_number' => $consumer->account_number])->assertForbidden();
    $this->get(route('provider.consumers.index'))->assertOk()->assertDontSee('Delete account');
    $portal = User::factory()->create(['consumer_id' => $consumer->id]);
    $this->actingAs($portal)->delete('/provider/consumers/'.$consumer->id, ['account_number' => $consumer->account_number])->assertForbidden();
    $this->assertDatabaseHas('consumers', ['id' => $consumer->id]);
});

test('delete confirmation is selected and cannot load another providers consumer', function () {
    $consumer = Consumer::factory()->create(['account_number' => 'ACC-009']);
    $other = Consumer::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $consumer->water_provider_id]);
    $this->actingAs($staff)->get(route('provider.consumers.index', ['delete_consumer' => $consumer->id]))
        ->assertOk()->assertSee('Delete account')->assertSee('Permanently delete account')
        ->assertSee('ACC-009')->assertSee('name="account_number"', false)->assertSee('value="DELETE"', false);
    $this->get(route('provider.consumers.index', ['delete_consumer' => $other->id]))->assertNotFound();
});

test('delete rolls back all dependent changes if the audit cannot be written', function () {
    $bill = Bill::factory()->create();
    $consumer = $bill->consumer;
    $portal = User::factory()->create(['consumer_id' => $consumer->id]);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $consumer->water_provider_id]);
    AuditLog::creating(function (AuditLog $log): void {
        if ($log->action === 'consumer.deleted') {
            throw new RuntimeException('Audit unavailable');
        }
    });
    $this->withoutExceptionHandling()->actingAs($staff);
    expect(fn () => $this->delete('/provider/consumers/'.$consumer->id, ['account_number' => $consumer->account_number]))
        ->toThrow(RuntimeException::class, 'Audit unavailable');
    $this->assertDatabaseHas('consumers', ['id' => $consumer->id]);
    $this->assertDatabaseHas('users', ['id' => $portal->id]);
    $this->assertDatabaseHas('bills', ['id' => $bill->id]);
    $this->assertDatabaseHas('connections', ['id' => $bill->connection_id]);
});
