<?php

namespace App\Console\Commands;

use App\Actions\ClientNoticeService;
use App\Models\Bill;
use Illuminate\Console\Command;

class SendClientReminders extends Command
{
    protected $signature = 'clients:reminders';

    protected $description = 'Create client inbox reminders for unpaid bills due within seven days or overdue';

    public function handle(ClientNoticeService $notices): int
    {
        Bill::with(['consumer.portalUser', 'allocations'])->where('status', '!=', 'void')
            ->whereDate('due_at', '<=', today()->addDays(7))
            ->whereRaw('total > (select coalesce(sum(amount), 0) from payment_allocations where bill_id = bills.id)')
            ->chunkById(100, function ($bills) use ($notices): void {
                foreach ($bills as $bill) {
                    $notices->remind($bill);
                }
            });
        $this->info('Client due-date and overdue reminders checked.');

        return self::SUCCESS;
    }
}
