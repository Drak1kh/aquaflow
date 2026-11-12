<?php

use App\Models\Bill;
use App\Models\Payment;
use Database\Seeders\FullDemoDataSeeder;

test('full demo seeding creates repeatable operational data with consistent bill balances', function () {
    $this->seed(FullDemoDataSeeder::class);
    $this->seed(FullDemoDataSeeder::class);

    foreach (['water_providers' => 2, 'consumers' => 24, 'connections' => 24, 'meter_readings' => 144,
        'bills' => 144, 'payments' => 120, 'support_tickets' => 8, 'ticket_messages' => 8,
        'water_rates' => 2, 'billing_periods' => 12, 'bill_adjustments' => 2,
    ] as $table => $count) {
        $this->assertDatabaseCount($table, $count);
    }

    foreach (Bill::with('allocations')->where('status', 'paid')->get() as $bill) {
        expect($bill->balance())->toBe('0.00');
    }
    foreach (Payment::with('allocations')->where('status', 'verified')->get() as $payment) {
        expect($payment->allocations->sum('amount'))->toEqual((float) $payment->amount);
    }
    $this->assertDatabaseHas('support_tickets', ['status' => 'under_review']);
    $this->assertDatabaseHas('connections', ['status' => 'disconnected']);
    $this->assertDatabaseHas('payments', ['status' => 'rejected']);
});

test('full demo seeding leaves production unchanged', function () {
    $environment = app()->environment();
    app()->instance('env', 'production');

    try {
        $this->seed(FullDemoDataSeeder::class);
    } finally {
        app()->instance('env', $environment);
    }

    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('bills', 0);
});
