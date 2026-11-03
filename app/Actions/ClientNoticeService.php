<?php

namespace App\Actions;

use App\Models\Bill;
use App\Models\ClientNotice;
use App\Models\Consumer;
use App\UserRole;

class ClientNoticeService
{
    public function send(Consumer $consumer, string $eventKey, string $title, string $body, string $url): void
    {
        $user = $consumer->portalUser;
        if (! $user || $user->role !== UserRole::Consumer) {
            return;
        }
        ClientNotice::firstOrCreate(['user_id' => $user->id, 'event_key' => $eventKey], ['consumer_id' => $consumer->id, ...compact('title', 'body', 'url')]);
    }

    public function remind(Bill $bill): void
    {
        if ($bill->status === 'void' || bccomp($bill->balance(), '0', 2) <= 0 || $bill->due_at->isAfter(today()->addDays(7))) {
            return;
        }
        $overdue = $bill->due_at->isBefore(today());
        $this->send($bill->consumer, ($overdue ? 'overdue:' : 'due:').$bill->id.':'.$bill->due_at->toDateString(),
            $overdue ? 'Overdue balance' : 'Payment due reminder',
            'Bill '.$bill->number.' is due '.$bill->due_at->format('M d, Y').'. Remaining balance when this reminder was generated: ₱'.$bill->balance().'.',
            route('bills.print', $bill, false));
    }
}
