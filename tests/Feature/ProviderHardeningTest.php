<?php

use App\Models\Bill;
use App\Models\Connection;
use App\Models\Consumer;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\ServiceEvent;
use App\Models\User;
use App\Models\WaterProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

test('consumer pages are paginated have descriptive titles and do not query unrelated modules', function () {
    $provider = WaterProvider::factory()->create();
    Consumer::factory()->count(23)->create(['water_provider_id' => $provider->id]);
    $admin = User::factory()->superAdmin()->create();
    DB::enableQueryLog();

    $response = $this->actingAs($admin)->get(route('provider.consumers.index'));

    $response->assertOk()->assertSee('Consumer Management -')->assertViewHas('consumers', fn ($consumers) => $consumers->total() === 23 && $consumers->count() === 20);
    $queries = collect(DB::getQueryLog())->pluck('query')->implode(' ');
    expect($queries)->not->toContain('from "bills"')->not->toContain('from "payments"')->not->toContain('from "meter_readings"');
    expect(substr_count($response->getContent(), 'data-modal="edit-consumer-'))->toBe(0);
});

test('only the selected consumer edit form is rendered and submitted values survive validation', function () {
    $consumer = Consumer::factory()->create();
    $other = Consumer::factory()->create(['water_provider_id' => $consumer->water_provider_id]);
    $admin = User::factory()->superAdmin()->create();
    $page = route('provider.consumers.index', ['edit_consumer' => $consumer->id]);

    $this->actingAs($admin)->get($page)->assertOk()->assertSee('data-modal="edit-consumer-'.$consumer->id.'"', false)->assertDontSee('data-modal="edit-consumer-'.$other->id.'"', false)->assertSee('initiallyOpen: true', false);
    $this->from($page)->put(route('provider.consumers.update', $consumer), ['_form' => 'edit-consumer-'.$consumer->id, 'name' => 'Unsaved revised name', 'email' => 'invalid', 'phone' => '09123456789', 'address' => 'Revised address'])->assertSessionHasErrors('email');
    $this->get($page)->assertOk()->assertSee('value="Unsaved revised name"', false)->assertSee('value="Revised address"', false);
    expect($consumer->fresh()->name)->toBe($consumer->name);
});

test('consumer details render provider-scoped filtered account activity with receipts', function () {
    $provider = WaterProvider::factory()->create();
    $consumer = Consumer::factory()->create(['water_provider_id' => $provider->id, 'account_number' => 'ACCOUNT-PREVIEW']);
    $connection = Connection::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'meter_number' => 'METER-PREVIEW']);
    MeterReading::factory()->create(['water_provider_id' => $provider->id, 'connection_id' => $connection->id, 'period_month' => '2026-10-01', 'previous_reading' => '10.000', 'current_reading' => '24.000']);
    $bill = Bill::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'connection_id' => $connection->id, 'number' => 'BILL-PREVIEW', 'period_month' => '2026-10-01', 'total' => '150.00']);
    $payment = Payment::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'bill_id' => $bill->id, 'reference' => 'PAY-PREVIEW', 'amount' => '50.00', 'status' => 'verified', 'paid_at' => '2026-10-15']);
    PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'bill_id' => $bill->id, 'amount' => '50.00']);
    Payment::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'bill_id' => $bill->id, 'reference' => 'PAY-PENDING', 'status' => 'pending', 'paid_at' => '2026-10-16']);
    Payment::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'bill_id' => $bill->id, 'reference' => 'PAY-REJECTED', 'status' => 'rejected', 'paid_at' => '2026-10-17']);
    Payment::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'bill_id' => $bill->id, 'reference' => 'PAY-NEXT-MONTH', 'paid_at' => '2026-11-01']);
    $otherConsumer = Consumer::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $provider->id]);

    $this->actingAs($staff)->get(route('provider.consumers.index', ['preview_consumer' => $consumer->id, 'period' => '2026-10']))
        ->assertOk()
        ->assertSee('Consumer Management')
        ->assertSee('Back to consumers')
        ->assertDontSee('Search consumer name or account')
        ->assertDontSee('data-modal="consumer-preview-'.$consumer->id.'"', false)
        ->assertSee('Account activity')
        ->assertSee('name="period"', false)
        ->assertDontSee('Meter-reading history')
        ->assertDontSee('Bill history')
        ->assertDontSee('Payment history')
        ->assertSee('ACCOUNT-PREVIEW')
        ->assertSee('METER-PREVIEW')
        ->assertSee('BILL-PREVIEW')
        ->assertSee('PAY-PREVIEW')
        ->assertSee('PAY-PENDING')
        ->assertSee('PAY-REJECTED')
        ->assertDontSee('PAY-NEXT-MONTH')
        ->assertSee('Verified')
        ->assertSee('Pending')
        ->assertSee('Rejected')
        ->assertSee(route('payments.print', $payment), false)
        ->assertSee('100.00');

    $this->get(route('provider.consumers.index', ['preview_consumer' => $otherConsumer->id]))->assertNotFound();
});

test('consumer actions include a view link to the account details', function () {
    $consumer = Consumer::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $consumer->water_provider_id]);

    $this->actingAs($staff)->get(route('provider.consumers.index'))
        ->assertOk()
        ->assertSee('View')
        ->assertSee('no-underline', false)
        ->assertSee(route('provider.consumers.index', ['preview_consumer' => $consumer->id]), false);
});

test('opening meter readings survive a validation failure and feedback is shown once', function () {
    $connection = Connection::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $connection->water_provider_id]);
    $page = route('provider.readings.index');

    $this->actingAs($staff)->from($page)->post(route('provider.readings.store'), ['_form' => 'create-reading', 'connection_id' => $connection->id, 'period_month' => '2026-10', 'previous_reading' => '100.000', 'current_reading' => '90.000'])->assertSessionHasErrors('current_reading');
    session()->reflash();
    session()->save();
    $this->get($page)->assertOk()->assertSee('value="100.000"', false);
    $this->assertDatabaseCount('meter_readings', 0);
});

test('locked readings and ineligible service actions are explained instead of offered', function () {
    $bill = Bill::factory()->create(['due_at' => today()->addMonth()]);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $bill->water_provider_id]);

    $this->actingAs($staff)->get(route('provider.readings.index'))->assertOk()->assertSee('Bill Generated')->assertDontSee('action="'.route('provider.readings.update', $bill->meter_reading_id).'"', false);
    $this->get(route('provider.service.index'))->assertOk()->assertSee('No overdue balance:')->assertDontSee('<option value="disconnect">', false)->assertDontSee('<option value="notice">', false);
});

test('meter reading history presents its readings in a table', function () {
    $reading = \App\Models\MeterReading::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $reading->water_provider_id]);

    $this->actingAs($staff)->get(route('provider.readings.index'))
        ->assertOk()
        ->assertSee('Previous')
        ->assertSee('Current')
        ->assertSee('Usage (m³)')
        ->assertDontSee('Correction')
        ->assertDontSee('Correct reading');
});

test('meter readings show provider-wide KPIs and consumption trend', function () {
    $this->travelTo('2026-11-05');
    $provider = WaterProvider::factory()->create();
    $activeConsumer = Consumer::factory()->create(['water_provider_id' => $provider->id]);
    $activeConnection = Connection::factory()->create(['consumer_id' => $activeConsumer->id, 'status' => 'active']);
    $inactiveConsumer = Consumer::factory()->create(['water_provider_id' => $provider->id]);
    $inactiveConnection = Connection::factory()->create(['consumer_id' => $inactiveConsumer->id, 'status' => 'disconnected']);
    MeterReading::factory()->create(['connection_id' => $activeConnection->id, 'period_month' => '2026-10-01', 'previous_reading' => '1.000', 'current_reading' => '6.000']);
    MeterReading::factory()->create(['connection_id' => $inactiveConnection->id, 'period_month' => '2026-11-01', 'previous_reading' => '2.000', 'current_reading' => '9.000']);
    MeterReading::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $provider->id]);

    $response = $this->actingAs($staff)->get(route('provider.readings.index', ['search' => 'No matching consumer', 'period' => '2026-10']));

    $response->assertOk()
        ->assertSee('Recorded readings')
        ->assertSee('Active connections')
        ->assertSee('Not Active connections')
        ->assertSee('Water consumption')
        ->assertSee('data-meter-reading-summary', false)
        ->assertDontSee('View monthly values')
        ->assertViewHas('recordedReadingCount', 2)
        ->assertViewHas('activeConnectionCount', 1)
        ->assertViewHas('inactiveConnectionCount', 1);
    expect(collect($response->viewData('consumptionTrend'))->firstWhere('month', 'Oct 2026')['consumption'])->toBe('5.000');
    expect(collect($response->viewData('consumptionTrend'))->firstWhere('month', 'Nov 2026')['consumption'])->toBe('7.000');
});

test('meter readings link consumers and generated bills while pending readings remain informational', function () {
    $bill = Bill::factory()->create();
    $pendingConsumer = Consumer::factory()->create(['water_provider_id' => $bill->water_provider_id]);
    $pendingConnection = Connection::factory()->create(['consumer_id' => $pendingConsumer->id]);
    MeterReading::factory()->create(['connection_id' => $pendingConnection->id]);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $bill->water_provider_id]);

    $this->actingAs($staff)->get(route('provider.readings.index'))
        ->assertOk()
        ->assertSee(route('provider.consumers.index', ['search' => $bill->consumer->account_number]), false)
        ->assertSee(route('provider.payments.index', ['bill' => $bill->id]), false)
        ->assertSee('Bill Generated')
        ->assertSee('Pending');
});

test('payment management shows the selected bill and only its payments', function () {
    $bill = Bill::factory()->create();
    $connection = Connection::findOrFail($bill->connection_id);
    $payment = Payment::factory()->create(['bill_id' => $bill->id, 'reference' => 'PAY-SELECTED']);
    $otherConsumer = Consumer::factory()->create(['water_provider_id' => $bill->water_provider_id]);
    $otherConnection = Connection::factory()->create(['consumer_id' => $otherConsumer->id]);
    $otherBill = Bill::factory()->create(['connection_id' => $otherConnection->id]);
    Payment::factory()->create(['bill_id' => $otherBill->id, 'reference' => 'PAY-OTHER']);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $bill->water_provider_id]);

    $this->actingAs($staff)->get(route('provider.payments.index', ['bill' => $bill->id]))
        ->assertOk()
        ->assertSee($bill->number)
        ->assertSee($connection->meter_number)
        ->assertSee('name="bill" value="'.$bill->id.'"', false)
        ->assertSee($payment->reference)
        ->assertDontSee('PAY-OTHER');
});

test('payment management rejects a selected bill from another provider', function () {
    $bill = Bill::factory()->create();
    $otherBill = Bill::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $bill->water_provider_id]);

    $this->actingAs($staff)->get(route('provider.payments.index', ['bill' => $otherBill->id]))
        ->assertRedirect()
        ->assertSessionHasErrors('bill');
});

test('search applies to connection tables and service history consistently', function () {
    $connection = Connection::factory()->create();
    $connection->consumer->update(['name' => 'Ada Rivera']);
    $otherConsumer = Consumer::factory()->create(['water_provider_id' => $connection->water_provider_id, 'name' => 'Other Consumer']);
    $other = Connection::factory()->create(['water_provider_id' => $connection->water_provider_id, 'consumer_id' => $otherConsumer->id]);
    ServiceEvent::factory()->create(['connection_id' => $other->id, 'reason' => 'Unrelated history']);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $connection->water_provider_id]);

    $this->actingAs($staff)->get(route('provider.readings.index', ['search' => 'Ada']))->assertOk()->assertSee('Ada Rivera')->assertDontSee('Other Consumer');
    $this->get(route('provider.service.index', ['search' => 'Ada']))->assertOk()->assertSee('Ada Rivera')->assertDontSee('Unrelated history');
});

test('provider pages render one shared validation summary', function () {
    WaterProvider::factory()->create();
    $admin = User::factory()->superAdmin()->create();
    $errors = (new ViewErrorBag)->put('default', new MessageBag(['name' => 'Enter the registered name.']));

    $this->actingAs($admin)->withSession(['errors' => $errors]);
    session()->save();
    $response = $this->get(route('provider.consumers.index'));

    $response->assertOk();
    expect(substr_count($response->getContent(), 'data-form-feedback='))->toBe(1);
});

test('connection operations show provider-scoped financial and service context', function () {
    $provider = WaterProvider::factory()->create();
    $consumer = Consumer::factory()->create(['water_provider_id' => $provider->id]);
    $active = Connection::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'status' => 'active']);
    Connection::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'status' => 'disconnected']);
    Connection::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'status' => 'pending_reconnection']);
    $bill = Bill::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'connection_id' => $active->id, 'due_at' => today()->subDay(), 'total' => '100.00']);
    Payment::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'bill_id' => $bill->id, 'status' => 'pending', 'reference' => 'PENDING-PROVIDER']);

    $otherBill = Bill::factory()->create(['due_at' => today()->subDay(), 'total' => '900.00']);
    Payment::factory()->create(['bill_id' => $otherBill->id, 'status' => 'pending', 'reference' => 'PENDING-OTHER']);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $provider->id]);

    $this->actingAs($staff)->get(route('provider.balances.index'))
        ->assertOk()
        ->assertViewHas('billCount', 1)
        ->assertViewHas('overdueBillCount', 1)
        ->assertSee('Outstanding balance')
        ->assertDontSee('Review payments')
        ->assertDontSee('PENDING-PROVIDER')
        ->assertDontSee('900.00');

    $this->get(route('provider.service.index'))
        ->assertOk()
        ->assertViewHas('serviceStatusCounts', ['active' => 1, 'disconnected' => 1, 'pending_reconnection' => 1])
        ->assertSee('Service lifecycle');

    $this->get(route('provider.payments.index'))
        ->assertOk()
        ->assertViewHas('pendingPaymentCount', 1)
        ->assertSee('Pending review')
        ->assertSee($bill->number)
        ->assertSee($active->meter_number)
        ->assertDontSee('PENDING-OTHER');
});
