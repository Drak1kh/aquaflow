<?php

use App\Models\Bill;
use App\Models\Payment;
use App\Models\User;

test('bill previews are branded standalone documents with preserved charges', function () {
    $bill = Bill::factory()->create(['total' => '150.25']);
    $client = User::factory()->create(['consumer_id' => $bill->consumer_id]);

    $this->actingAs($client)->get(route('bills.print', ['bill' => $bill, 'preview' => 1]))
        ->assertOk()->assertSee('data-print-document', false)->assertSee('alt="AquaFlow"', false)
        ->assertSee($bill->number)->assertSee($bill->consumer->account_number)
        ->assertSee('₱150.25')->assertSee('Charges and meter readings')
        ->assertDontSee('<div class="document-toolbar">', false)->assertDontSee('data-flux-sidebar', false);

    $this->get(route('bills.print', $bill))->assertOk()->assertSee('<div class="document-toolbar">', false);
});

test('receipt previews remain private and require verified payments', function () {
    $bill = Bill::factory()->create();
    $payment = Payment::factory()->create(['bill_id' => $bill->id, 'amount' => '40.00', 'status' => 'pending']);
    $client = User::factory()->create(['consumer_id' => $bill->consumer_id]);

    $this->actingAs($client)->get(route('payments.print', ['payment' => $payment, 'preview' => 1]))->assertNotFound();
    $payment->update(['status' => 'verified']);
    $this->get(route('payments.print', ['payment' => $payment, 'preview' => 1]))
        ->assertOk()->assertSee('Payment receipt')->assertSee($payment->reference)->assertSee('₱40.00')->assertSee('alt="AquaFlow"', false);

    $other = User::factory()->create(['consumer_id' => Bill::factory()->create()->consumer_id]);
    $this->actingAs($other)->get(route('payments.print', ['payment' => $payment, 'preview' => 1]))->assertNotFound();
    $this->get(route('bills.print', ['bill' => $bill, 'preview' => 1]))->assertNotFound();
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
