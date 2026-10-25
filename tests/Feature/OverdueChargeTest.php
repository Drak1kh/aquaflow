<?php

use App\Models\Bill;
use App\Models\BillAdjustment;
use App\Models\WaterProvider;

test('overdue fee is applied once and updates the balance', function () {
    $provider = WaterProvider::factory()->create(['late_fee' => '10.00', 'grace_days' => 3]);
    $bill = Bill::factory()->create(['water_provider_id' => $provider->id, 'due_on' => now()->subDays(5)->toDateString()]);

    $this->artisan('aquaflow:apply-overdue')->assertSuccessful();
    $this->artisan('aquaflow:apply-overdue')->assertSuccessful();

    expect(BillAdjustment::where('bill_id', $bill->id)->count())->toBe(1)
        ->and($bill->fresh()->balanceCents())->toBe(8500)
        ->and($bill->fresh()->status)->toBe('overdue');
});
