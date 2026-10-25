<?php

use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\Connection;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\RolePermission;
use App\Models\SupportTicket;
use App\Models\SystemSetting;
use App\Models\TicketMessage;
use App\Models\WaterRate;

it('creates internally consistent operational records through their factories', function () {
    $bill = Bill::factory()->create();
    $payment = Payment::factory()->create(['bill_id' => $bill->id]);
    $allocation = PaymentAllocation::factory()->create(['payment_id' => $payment->id]);
    $ticket = SupportTicket::factory()->create();
    $message = TicketMessage::factory()->create(['support_ticket_id' => $ticket->id]);
    $rate = WaterRate::factory()->create(['water_provider_id' => $bill->water_provider_id]);
    $setting = SystemSetting::factory()->create();
    $permission = RolePermission::factory()->create();
    $event = AuditLog::factory()->create();

    expect($bill->meter_reading_id)->toBeGreaterThan(0)
        ->and($bill->consumer_id)->toBe(Connection::findOrFail($bill->connection_id)->consumer_id)
        ->and($payment->water_provider_id)->toBe($bill->water_provider_id)
        ->and($allocation->bill_id)->toBe($bill->id)
        ->and($message->support_ticket_id)->toBe($ticket->id)
        ->and($rate->water_provider_id)->toBe($bill->water_provider_id)
        ->and($setting->value)->not->toBeEmpty()
        ->and($permission->allowed)->toBeTrue()
        ->and($event->subject_id)->not->toBeEmpty();
});
