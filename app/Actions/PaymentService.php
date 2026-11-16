<?php

namespace App\Actions;

use App\Models\Bill;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(private AuditLogger $audit) {}

    public function verify(Payment $payment, User $actor): void
    {
        DB::transaction(function () use ($payment, $actor): void {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $items = $payment->items()->orderBy('id')->lockForUpdate()->get();
            $plan = $items->isNotEmpty() ? $items->map(fn ($item) => ['bill_id' => $item->bill_id, 'amount' => $item->amount]) : collect([['bill_id' => $payment->bill_id, 'amount' => $payment->amount]]);
            $bills = Bill::whereIn('id', $plan->pluck('bill_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $sum = $plan->reduce(fn ($sum, $item) => bcadd($sum, $item['amount'], 2), '0.00');
            if ($payment->status !== 'pending' || bccomp($payment->amount, '0.00', 2) <= 0 || bccomp($sum, $payment->amount, 2) !== 0 || ($actor->role !== UserRole::SuperAdmin && ($actor->role !== UserRole::WaterProvider || $actor->water_provider_id !== $payment->water_provider_id))) {
                throw ValidationException::withMessages(['payment' => 'This payment cannot be verified against the bill balances.']);
            }

            foreach ($plan as $item) {
                $bill = $bills->get($item['bill_id']);
                if (! $bill || $bill->status === 'void' || $payment->water_provider_id !== $bill->water_provider_id || $payment->consumer_id !== $bill->consumer_id || bccomp($item['amount'], '0.00', 2) <= 0 || bccomp($item['amount'], $bill->balance(), 2) > 0) {
                    throw ValidationException::withMessages(['payment' => 'A selected bill has changed. Review the payment and bill balances before verifying.']);
                }
            }

            $dateFormat = app(PlatformSettings::class)->get('date_format');
            $snapshotBills = [];
            foreach ($plan as $item) {
                $bill = $bills->get($item['bill_id']);
                $before = $bill->balance();
                PaymentAllocation::create(['payment_id' => $payment->id, 'bill_id' => $bill->id, 'amount' => $item['amount']]);
                $bill->unsetRelation('allocations');
                $after = $bill->balance();
                $bill->status = bccomp($after, '0.00', 2) === 0 ? 'paid' : 'partially_paid';
                $bill->save();
                $snapshotBills[] = [
                    ...$bill->only(['id', 'number', 'previous_reading', 'current_reading', 'consumption', 'base_charge', 'unit_rate', 'consumption_charge', 'late_fee', 'total']),
                    'period_month' => $bill->period_month->toDateString(), 'month' => $bill->period_month->format('F Y'), 'due_date' => $bill->due_at->format($dateFormat),
                    'connection_id' => $bill->connection_id, 'connection_number' => $bill->connection->number, 'meter_number' => $bill->connection->meter_number,
                    'adjustments' => $bill->adjustments->map(fn ($adjustment) => ['description' => ucfirst($adjustment->type).' adjustment · '.$adjustment->reason, 'amount' => $adjustment->type === 'credit' ? bcsub('0.00', $adjustment->amount, 2) : $adjustment->amount])->all(),
                    'applied' => $item['amount'], 'balance_before' => $before, 'balance_after' => $after, 'status' => $bill->paymentStatus(),
                ];
            }
            $payment->status = 'verified';
            $payment->setAttribute('verified_by', $actor->id);
            $payment->verified_at = now();
            $payment->checked_at = now();
            $payment->receipt_snapshot = [
                'version' => 1, 'reference' => $payment->reference, 'amount' => $payment->amount, 'method' => ucfirst(str_replace('_', ' ', $payment->method)),
                'payment_date' => $payment->paid_at->format($dateFormat), 'verified_date' => $payment->verified_at->format($dateFormat.' g:i A'),
                'issuer' => $payment->waterProvider->only(['id', 'name', 'address', 'phone', 'email']),
                'consumer' => $payment->consumer->only(['id', 'account_number', 'name', 'address', 'phone', 'email']),
                'bills' => $snapshotBills,
                'total_charges' => collect($snapshotBills)->reduce(fn ($sum, $bill) => bcadd($sum, $bill['total'], 2), '0.00'),
                'remaining_balance' => collect($snapshotBills)->reduce(fn ($sum, $bill) => bcadd($sum, $bill['balance_after'], 2), '0.00'),
            ];
            $payment->save();
            $this->audit->record($payment, 'payment.verified', $actor, after: ['amount' => $payment->amount, 'bill_ids' => $plan->pluck('bill_id')->all()]);
        });
    }
}
