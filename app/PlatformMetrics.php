<?php

namespace App;

use App\Models\Bill;
use App\Models\BillAdjustment;
use App\Models\Payment;
use App\Models\PaymentAllocation;

class PlatformMetrics
{
    public function billedCents(?int $providerId = null): int
    {
        $amount = Bill::query()
            ->where('status', '!=', 'void')
            ->when($providerId, fn ($query) => $query->where('water_provider_id', $providerId))
            ->sum('total_amount');

        return DecimalAmount::units((string) $amount, 2);
    }

    public function collectedCents(?int $providerId = null): int
    {
        $amount = Payment::query()
            ->where('status', 'verified')
            ->when($providerId, fn ($query) => $query->where('water_provider_id', $providerId))
            ->sum('amount');

        return DecimalAmount::units((string) $amount, 2);
    }

    public function outstandingCents(?int $providerId = null): int
    {
        $activeBills = Bill::query()
            ->where('status', '!=', 'void')
            ->when($providerId, fn ($query) => $query->where('water_provider_id', $providerId));

        $billed = DecimalAmount::units((string) (clone $activeBills)->sum('total_amount'), 2);
        $adjustments = DecimalAmount::units((string) BillAdjustment::query()
            ->whereIn('bill_id', (clone $activeBills)->select('id'))
            ->sum('amount'), 2);
        $allocated = DecimalAmount::units((string) PaymentAllocation::query()
            ->whereIn('bill_id', (clone $activeBills)->select('id'))
            ->whereHas('payment', fn ($query) => $query->where('status', 'verified'))
            ->sum('amount'), 2);

        return max(0, $billed + $adjustments - $allocated);
    }

    /** @return array<int, int> */
    public function outstandingByProvider(): array
    {
        $billed = Bill::query()->where('status', '!=', 'void')
            ->selectRaw('water_provider_id, SUM(total_amount) as total')
            ->groupBy('water_provider_id')->pluck('total', 'water_provider_id');
        $adjustments = BillAdjustment::query()
            ->whereIn('bill_id', Bill::query()->where('status', '!=', 'void')->select('id'))
            ->selectRaw('water_provider_id, SUM(amount) as total')
            ->groupBy('water_provider_id')->pluck('total', 'water_provider_id');
        $allocated = PaymentAllocation::query()
            ->join('bills', 'bills.id', '=', 'payment_allocations.bill_id')
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->where('bills.status', '!=', 'void')
            ->where('payments.status', 'verified')
            ->selectRaw('bills.water_provider_id as provider_id, SUM(payment_allocations.amount) as total')
            ->groupBy('bills.water_provider_id')->pluck('total', 'provider_id');

        $balances = [];
        foreach ($billed as $providerId => $amount) {
            $balances[(int) $providerId] = max(0,
                DecimalAmount::units((string) $amount, 2)
                + DecimalAmount::units((string) ($adjustments[$providerId] ?? '0'), 2)
                - DecimalAmount::units((string) ($allocated[$providerId] ?? '0'), 2),
            );
        }

        return $balances;
    }
}
