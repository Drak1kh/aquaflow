<?php

namespace App;

use App\Models\Bill;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentAllocator
{
    public function verify(Payment $payment, int $userId): void
    {
        DB::transaction(function () use ($payment, $userId): void {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->status !== 'pending') {
                throw ValidationException::withMessages(['payment' => 'Only pending payments can be verified.']);
            }

            $bill = Bill::whereKey($payment->intended_bill_id)->lockForUpdate()->firstOrFail();
            if ($bill->water_provider_id !== $payment->water_provider_id || $bill->consumer_id !== $payment->consumer_id || $bill->status === 'void') {
                throw ValidationException::withMessages(['payment' => 'The target bill is unavailable.']);
            }

            $amount = DecimalAmount::units((string) $payment->amount, 2);
            if ($amount > $bill->balanceCents()) {
                throw ValidationException::withMessages(['payment' => 'Payment exceeds the remaining bill balance.']);
            }

            PaymentAllocation::create(['payment_id' => $payment->id, 'bill_id' => $bill->id, 'amount' => $payment->amount]);
            $payment->update(['status' => 'verified', 'verified_by' => $userId, 'verified_at' => now()]);
            $bill->refreshStatus();
        });
    }
}
