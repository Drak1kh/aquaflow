<?php

use App\Actions\PaymentService;
use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Payment;
use App\Models\User;
use App\Models\WaterProvider;

test('bill previews preserve their original unpaid status and emphasize due dates', function () {
    $bill = Bill::factory()->create(['total' => '150.25', 'due_at' => today()->subDay()]);
    $client = User::factory()->create(['consumer_id' => $bill->consumer_id]);

    $this->actingAs($client)->get(route('bills.print', ['bill' => $bill, 'preview' => 1]))
        ->assertOk()->assertSee('data-print-document', false)->assertSee('alt="AquaFlow"', false)
        ->assertSee($bill->number)->assertSee($bill->consumer->account_number)
        ->assertSee('Original bill — unpaid')->assertSee('₱150.25')->assertSee('Charges and meter readings')
        ->assertSee('class="document-due-date"', false)
        ->assertSee('class="document-status document-status-unpaid"', false)
        ->assertSee('.document-status-paid { color: #15803d; }', false)
        ->assertSee('.document-status-unpaid { color: #a16207; }', false)
        ->assertSee('.document-status-overdue { color: #b91c1c; }', false)
        ->assertSee('.embedded .document { width: 100%; max-width: none; margin: 0; border: 0; }', false)
        ->assertDontSee('<div class="document-toolbar">', false)->assertDontSee('data-flux-sidebar', false);

    $this->get(route('bills.print', $bill))->assertOk()->assertSee('<div class="document-toolbar">', false);
});

test('verified payments have separate receipts while bill previews preserve the original unpaid document', function (string $amount, string $title, string $balance, string $statusClass) {
    $bill = Bill::factory()->create();
    $payment = Payment::factory()->create(['bill_id' => $bill->id, 'amount' => $amount]);
    $client = User::factory()->create(['consumer_id' => $bill->consumer_id]);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $bill->water_provider_id]);

    app(PaymentService::class)->verify($payment, $staff);

    expect($payment->fresh()->status)->toBe('verified');
    expect($bill->fresh()->balance())->toBe($balance);

    foreach ([$client, $staff] as $user) {
        $this->actingAs($user)->get(route('bills.print', ['bill' => $bill, 'preview' => 1]))
            ->assertOk()->assertSee('<h1>Original bill — unpaid</h1>', false)
            ->assertSee($bill->number)->assertDontSee($payment->reference)
            ->assertSee('class="document-status document-status-unpaid">Unpaid</dd>', false)
            ->assertSee('<p>Amount due</p><strong>₱150.00</strong>', false)
            ->assertSee('<th scope="row">Amount due</th><td class="amount">₱150.00</td>', false)
            ->assertDontSee('>Paid</dd>', false)->assertDontSee('>Partially Paid</dd>', false);

        $this->get(route('payments.print', ['payment' => $payment, 'preview' => 1]))
            ->assertOk()->assertSee('<h1>'.$title.'</h1>', false)
            ->assertSee('Payment received — verified')->assertSee($payment->reference)->assertSee('₱'.$amount)
            ->assertSee('class="document-due-date"', false)
            ->assertSee('class="document-status document-status-'.$statusClass.'"', false)
            ->assertSee('<th scope="row">Remaining balance</th><td class="amount">₱'.$balance.'</td>', false)
            ->assertDontSee('Original bill — unpaid')->assertSee('alt="AquaFlow"', false);

        if ($balance !== '0.00') {
            $this->get(route('payments.print', $payment))->assertDontSee('Paid receipt')->assertDontSee('>Paid</dd>', false);
        }
    }

    $this->actingAs($client)->get(route('portal.section', 'payments'))
        ->assertOk()->assertSee('View payment receipt')->assertDontSee('View paid receipt');
    $this->actingAs($staff)->get(route('provider.payments.index'))
        ->assertOk()->assertSee('Original bill')->assertSee('Payment receipt')
        ->assertSee('View original bill')->assertSee('View payment receipt')->assertDontSee('View paid receipt');
})->with([
    'full payment' => ['150.00', 'Paid receipt', '0.00', 'paid'],
    'partial payment' => ['40.00', 'Payment received', '110.00', 'partial'],
]);

test('pending and rejected payment receipts remain unavailable', function (string $status) {
    $payment = Payment::factory()->create();
    $client = User::factory()->create(['consumer_id' => $payment->consumer_id]);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $payment->water_provider_id]);

    if ($status === 'rejected') {
        $this->actingAs($staff)->post(route('provider.payments.reject', $payment), ['remarks' => 'Proof could not be confirmed.'])
            ->assertSessionHasNoErrors();
    }

    expect($payment->fresh()->status)->toBe($status);
    foreach ([$client, $staff] as $user) {
        $this->actingAs($user)->get(route('payments.print', ['payment' => $payment, 'preview' => 1]))->assertNotFound();
    }
})->with(['pending', 'rejected']);

test('original bill and verified payment documents remain private across consumers and providers', function () {
    $payment = Payment::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $payment->water_provider_id]);
    app(PaymentService::class)->verify($payment, $staff);
    $otherConsumer = Consumer::factory()->create(['water_provider_id' => $payment->water_provider_id]);
    $otherClient = User::factory()->create(['consumer_id' => $otherConsumer->id]);
    $otherStaff = User::factory()->waterProvider()->create(['water_provider_id' => WaterProvider::factory()->create()->id]);

    foreach ([$otherClient, $otherStaff] as $user) {
        $this->actingAs($user)->get(route('payments.print', ['payment' => $payment, 'preview' => 1]))->assertNotFound();
        $this->get(route('bills.print', ['bill' => $payment->bill_id, 'preview' => 1]))->assertNotFound();
    }
});

test('void bill documents are explicitly labelled void', function () {
    $bill = Bill::factory()->create(['status' => 'void']);
    $client = User::factory()->create(['consumer_id' => $bill->consumer_id]);

    $this->actingAs($client)->get(route('bills.print', ['bill' => $bill, 'preview' => 1]))
        ->assertOk()->assertSee('<h1>Original bill — void</h1>', false)
        ->assertSee('class="document-status document-status-void">Void</dd>', false)
        ->assertDontSee('Original bill — unpaid');
});

test('report previews retain the selected provider dates and records', function () {
    $admin = User::factory()->superAdmin()->create();
    $bill = Bill::factory()->create(['number' => 'INCLUDED-BILL', 'period_month' => '2026-10-01']);
    Bill::factory()->create(['number' => 'OTHER-PROVIDER', 'period_month' => '2026-10-01']);

    $this->actingAs($admin)->get(route('admin.reports.print', [
        'type' => 'billing', 'provider' => $bill->water_provider_id, 'from' => '2026-10-01', 'to' => '2026-10-31', 'preview' => 1,
    ]))->assertOk()->assertSee('Billing report')->assertSee('alt="AquaFlow"', false)
        ->assertSee($bill->waterProvider->name)->assertSee('2026-10-01')->assertSee('2026-10-31')
        ->assertSee('INCLUDED-BILL')->assertDontSee('OTHER-PROVIDER');
});

test('document actions open the shared modal on consumer provider and admin pages', function () {
    $bill = Bill::factory()->create();
    Payment::factory()->create(['bill_id' => $bill->id, 'status' => 'verified']);
    $client = User::factory()->create(['consumer_id' => $bill->consumer_id]);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $bill->water_provider_id]);
    $admin = User::factory()->superAdmin()->create();

    foreach (['bills', 'payments'] as $section) {
        $this->actingAs($client)->get(route('portal.section', $section))->assertOk()
            ->assertSee('data-document-preview', false)->assertSee('data-modal="document-preview"', false);
    }
    foreach (['provider.billing.index', 'provider.payments.index'] as $route) {
        $this->actingAs($staff)->get(route($route))->assertOk()
            ->assertSee('data-document-preview', false)->assertSee('data-modal="document-preview"', false);
    }
    $this->actingAs($admin)->get(route('admin.reports.index'))->assertOk()
        ->assertSee('data-document-preview', false)->assertDontSee('target="_blank"', false);
    $this->get(route('admin.billing.show', $bill))->assertOk()->assertSee('View / print bill');
});
