<?php

namespace App\Console\Commands;

use App\AuditRecorder;
use App\DecimalAmount;
use App\Models\Bill;
use App\Models\BillAdjustment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

#[Signature('aquaflow:apply-overdue')]
#[Description('Mark overdue bills and apply each provider late fee once')]
class ApplyOverdueCharges extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(AuditRecorder $audit): int
    {
        Bill::with('provider')->whereNotIn('status', ['paid', 'void'])->chunkById(100, function ($bills) use ($audit): void {
            foreach ($bills as $bill) {
                DB::transaction(function () use ($bill, $audit): void {
                    $bill = Bill::whereKey($bill->id)->lockForUpdate()->firstOrFail();
                    if ($bill->status === 'void' || $bill->balanceCents() === 0) {
                        $bill->refreshStatus();

                        return;
                    }

                    $provider = $bill->provider;
                    if (Carbon::parse($bill->due_on)->addDays($provider->grace_days)->isPast() && ! $bill->late_fee_applied_at && DecimalAmount::units((string) $provider->late_fee, 2) > 0) {
                        $adjustment = BillAdjustment::create([
                            'water_provider_id' => $provider->id,
                            'bill_id' => $bill->id,
                            'authorized_by' => null,
                            'amount' => $provider->late_fee,
                            'reason' => 'Automatic overdue fee',
                        ]);
                        $bill->update(['late_fee_applied_at' => now()]);
                        $audit->record($adjustment, 'bill.late_fee.applied');
                    }

                    $bill->refreshStatus();
                });
            }
        });

        return self::SUCCESS;
    }
}
