<?php

use App\Models\Bill;
use App\Models\BillingPeriod;
use App\Models\ClientNotice;
use App\Models\Connection;
use App\Models\Consumer;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\ServiceEvent;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\WaterProvider;
use App\Models\WaterRate;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\Hash;

test('demo cleanup keeps only the three role credentials and removes demo records', function () {
    $this->seed(DemoDataSeeder::class);

    $provider = WaterProvider::where('code', 'demo-water')->firstOrFail();
    $consumer = Consumer::where('account_number', 'DEMO-001')->firstOrFail();
    $portalUser = User::where('email', 'test@example.com')->firstOrFail();
    $connection = Connection::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id]);
    $reading = MeterReading::factory()->create(['water_provider_id' => $provider->id, 'connection_id' => $connection->id]);
    $bill = Bill::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'connection_id' => $connection->id, 'meter_reading_id' => $reading->id]);
    $payment = Payment::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'bill_id' => $bill->id]);
    PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'bill_id' => $bill->id]);
    ServiceEvent::factory()->create(['connection_id' => $connection->id]);
    $ticket = SupportTicket::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'opened_by' => $portalUser->id]);
    TicketMessage::factory()->create(['support_ticket_id' => $ticket->id, 'author_id' => $portalUser->id]);
    ClientNotice::factory()->create(['user_id' => $portalUser->id, 'consumer_id' => $consumer->id]);
    WaterRate::factory()->create(['water_provider_id' => $provider->id]);
    BillingPeriod::factory()->create(['water_provider_id' => $provider->id]);

    $extraProvider = WaterProvider::factory()->create(['code' => 'riverside-demo']);
    $extraConsumer = Consumer::factory()->create(['water_provider_id' => $extraProvider->id]);
    User::factory()->waterProvider()->create(['water_provider_id' => $extraProvider->id]);
    User::factory()->create(['consumer_id' => $extraConsumer->id]);

    $this->artisan('demo:clear-data --force')->assertSuccessful();

    expect(User::whereIn('email', ['admin@example.com', 'provider@example.com', 'test@example.com'])->count())->toBe(3)
        ->and(User::count())->toBe(3)
        ->and(WaterProvider::where('code', 'demo-water')->count())->toBe(1)
        ->and(WaterProvider::count())->toBe(1)
        ->and(Consumer::where('account_number', 'DEMO-001')->count())->toBe(1)
        ->and(Consumer::count())->toBe(1)
        ->and(Bill::count())->toBe(0)
        ->and(Payment::count())->toBe(0)
        ->and(Hash::check('password', User::where('email', 'admin@example.com')->firstOrFail()->password))->toBeTrue()
        ->and(Hash::check('password', User::where('email', 'provider@example.com')->firstOrFail()->password))->toBeTrue()
        ->and(Hash::check('password', User::where('email', 'test@example.com')->firstOrFail()->password))->toBeTrue();

    foreach (['connections', 'meter_readings', 'payment_allocations', 'support_tickets', 'ticket_messages', 'service_events', 'client_notices', 'water_rates', 'billing_periods'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
});
