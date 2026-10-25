<?php

namespace App\Actions;

use App\Models\Bill;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(private AuditLogger $audit) {}

    public function verify(Payment $payment, User $actor): void
    {
        DB::transaction(function () use ($payment, $actor): void {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $bill = Bill::whereKey($payment->bill_id)->lockForUpdate()->firstOrFail();

            if ($payment->status !== 'pending' || $bill->status === 'void' || $payment->water_provider_id !== $bill->water_provider_id || $payment->consumer_id !== $bill->consumer_id || bccomp($payment->amount, $bill->balance(), 2) > 0) {
                throw ValidationException::withMessages(['payment' => 'This payment cannot be verified against the bill balance.']);
            }

            PaymentAllocation::create(['payment_id' => $payment->id, 'bill_id' => $bill->id, 'amount' => $payment->amount]);
            $payment->status = 'verified';
            $payment->setAttribute('verified_by', $actor->id);
            $payment->verified_at = now();
            $payment->save();
            $bill->unsetRelation('allocations');
            $bill->status = bccomp($bill->balance(), '0.00', 2) === 0 ? 'paid' : 'partially_paid';
            $bill->save();
            $this->audit->record($payment, 'payment.verified', $actor, after: ['amount' => $payment->amount, 'bill_id' => $bill->id]);
        });
    }
}
