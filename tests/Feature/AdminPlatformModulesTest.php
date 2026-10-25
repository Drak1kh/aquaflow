<?php

use App\Models\Bill;
use App\Models\BillAdjustment;
use App\Models\BillingPeriod;
use App\Models\Consumer;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\SupportTicket;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WaterProvider;

test('admin dashboard totals include verified collections and adjusted outstanding balances', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $provider = WaterProvider::factory()->create();
    $consumer = Consumer::factory()->create(['water_provider_id' => $provider->id]);
    $bill = Bill::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'total_amount' => '100.00']);
    BillAdjustment::factory()->create(['water_provider_id' => $provider->id, 'bill_id' => $bill->id, 'amount' => '10.00']);
    $payment = Payment::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'intended_bill_id' => $bill->id, 'amount' => '30.00', 'status' => 'verified']);
    PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'bill_id' => $bill->id, 'amount' => '30.00']);
    Payment::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'intended_bill_id' => $bill->id, 'amount' => '20.00', 'status' => 'rejected']);

    $this->actingAs($admin)->get(route('admin.index'))
        ->assertOk()
        ->assertViewHas('paymentCount', 1)
        ->assertViewHas('billedCents', 10000)
        ->assertViewHas('collectedCents', 3000)
        ->assertViewHas('outstandingCents', 8000);
});

test('platform oversight filters billing periods and payment method and status', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $provider = WaterProvider::factory()->create();
    $consumer = Consumer::factory()->create(['water_provider_id' => $provider->id]);
    $period = BillingPeriod::factory()->create(['water_provider_id' => $provider->id]);
    $otherPeriod = BillingPeriod::factory()->create([
        'water_provider_id' => $provider->id,
        'starts_on' => now()->subMonth()->startOfMonth()->toDateString(),
        'ends_on' => now()->subMonth()->endOfMonth()->toDateString(),
    ]);
    $bill = Bill::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'billing_period_id' => $period->id, 'bill_number' => 'B-MATCH']);
    $otherBill = Bill::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'billing_period_id' => $otherPeriod->id, 'bill_number' => 'B-OTHER']);
    Payment::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'intended_bill_id' => $bill->id, 'method' => 'cash', 'status' => 'verified', 'reference_number' => 'P-MATCH']);
    Payment::factory()->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'intended_bill_id' => $otherBill->id, 'method' => 'e_wallet', 'status' => 'rejected', 'reference_number' => 'P-OTHER']);

    $response = $this->actingAs($admin)->get(route('admin.overview', [
        'water_provider_id' => $provider->id,
        'billing_period_id' => $period->id,
        'payment_method' => 'cash',
        'payment_status' => 'verified',
    ]));

    $response->assertOk();
    expect($response['bills']->total())->toBe(1);
    expect($response['payments']->total())->toBe(1);
    expect($response['bills']->pluck('bill_number')->all())->toBe(['B-MATCH']);
    expect($response['payments']->pluck('reference_number')->all())->toBe(['P-MATCH']);
});

test('platform reports show current balances and export provider data', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $staff = User::factory()->create();
    $provider = WaterProvider::factory()->create(['name' => 'North Water', 'code' => 'north']);
    $consumer = Consumer::factory()->create(['water_provider_id' => $provider->id]);
    Bill::factory()->recycle([$provider, $consumer])->create(['water_provider_id' => $provider->id, 'consumer_id' => $consumer->id, 'total_amount' => '75.00']);

    $this->actingAs($staff)->get(route('admin.reports'))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.reports'))
        ->assertOk()
        ->assertViewHas('consumerCount', 1)
        ->assertViewHas('outstandingCents', 7500);

    $response = $this->get(route('admin.reports.export', ['type' => 'providers']));
    $response->assertOk()->assertDownload('aquaflow-providers-report.csv');
    expect($response->streamedContent())->toContain('North Water');
});

test('only an active super admin can assign and triage escalated tickets', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $assignee = User::factory()->create(['is_super_admin' => true]);
    $staff = User::factory()->create();
    $ticket = SupportTicket::factory()->create(['escalated_at' => now(), 'status' => 'escalated']);

    $this->actingAs($staff)->post(route('admin.support.assign', $ticket), ['assigned_to' => $assignee->id])->assertNotFound();
    $this->actingAs($admin)->post(route('admin.support.assign', $ticket), ['assigned_to' => $staff->id])->assertSessionHasErrors('assigned_to');
    expect($ticket->fresh()->assigned_to)->toBeNull();

    $this->post(route('admin.support.assign', $ticket), ['assigned_to' => $assignee->id])->assertRedirect();
    expect($ticket->fresh()->assigned_to)->toBe($assignee->id);

    $this->post(route('admin.support.status', $ticket), ['status' => 'referred_to_provider'])->assertRedirect();
    expect($ticket->fresh()->status)->toBe('referred_to_provider');
});

test('system settings supply provider fees and consumer notification defaults', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $consumer = Consumer::factory()->create(['notification_preferences' => null]);

    $this->actingAs($admin)->put(route('admin.settings.update'), [
        'platform_name' => 'AquaFlow',
        'support_email' => 'support@example.test',
        'default_grace_days' => 2,
        'default_disconnection_days' => 30,
        'default_late_fee' => '5.00',
        'default_reconnection_fee' => '15.00',
        'notification_billing_default' => '0',
        'notification_payment_default' => '1',
        'notification_support_default' => '1',
    ])->assertSessionHasNoErrors();

    expect(SystemSetting::setting('default_reconnection_fee', '0.00'))->toBe('15.00');
    expect($consumer->notificationEnabled('billing'))->toBeFalse();
    expect($consumer->notificationEnabled('payment'))->toBeTrue();
    expect($consumer->notificationEnabled('service'))->toBeTrue();
});
