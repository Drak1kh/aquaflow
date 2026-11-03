<?php

namespace App\Observers;

use App\Actions\ClientNoticeService;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\ServiceEvent;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\User;
use App\UserRole;
use Illuminate\Database\Eloquent\Model;

class ClientNoticeObserver
{
    public function __construct(private ClientNoticeService $notices) {}

    public function created(Model $record): void
    {
        if ($record instanceof User && $record->role === UserRole::Consumer && $record->consumer_id) {
            $this->existingBills($record);
        } elseif ($record instanceof Bill && $record->status !== 'void') {
            $this->notices->send($record->consumer, 'bill:'.$record->id, 'New water bill', 'Bill '.$record->number.' for '.$record->period_month->format('M Y').' is ready. Total: ₱'.$record->total.'. Due '.$record->due_at->format('M d, Y').'.', route('bills.print', $record, false));
        } elseif ($record instanceof Payment) {
            $this->payment($record);
        } elseif ($record instanceof ServiceEvent) {
            $title = match ($record->action) {
                'notice' => 'Disconnection notice', 'disconnect' => 'Service disconnected', 'request_reconnection' => 'Reconnection requested', 'restore' => 'Service restored', default => 'Connection update'
            };
            $this->notices->send($record->connection->consumer, 'service:'.$record->id, $title, $record->reason, route('portal.section', ['section' => 'connection', 'connection' => $record->connection_id], false));
        } elseif ($record instanceof TicketMessage && ! $record->internal) {
            $ticket = $record->supportTicket;
            if ($record->author_id !== $ticket->consumer->portalUser?->id) {
                $this->notices->send($ticket->consumer, 'reply:'.$record->id, 'Support response', 'There is a new response to: '.$ticket->subject, route('portal.tickets.show', $ticket, false));
            }
        }
    }

    public function updated(Model $record): void
    {
        if ($record instanceof User && $record->wasChanged('consumer_id') && $record->role === UserRole::Consumer && $record->consumer_id) {
            $this->existingBills($record);
        } elseif ($record instanceof Payment && $record->wasChanged('status')) {
            $this->payment($record);
        } elseif ($record instanceof SupportTicket && $record->wasChanged('status')) {
            $this->notices->send($record->consumer, 'ticket:'.$record->id.':'.$record->status.':'.$record->updated_at->format('YmdHis'), 'Support request updated', $record->subject.' is now '.str_replace('_', ' ', $record->status).'.', route('portal.tickets.show', $record, false));
        }
    }

    private function existingBills(User $user): void
    {
        Bill::with('consumer.portalUser')->where('consumer_id', $user->consumer_id)->where('status', '!=', 'void')->whereRaw('total > (select coalesce(sum(amount), 0) from payment_allocations where bill_id = bills.id)')
            ->chunkById(100, function ($bills): void {
                foreach ($bills as $bill) {
                    $this->notices->send($bill->consumer, 'bill:'.$bill->id, 'Water bill available', 'Bill '.$bill->number.' is available in your account.', route('bills.print', $bill, false));
                    $this->notices->remind($bill);
                }
            });
    }

    private function payment(Payment $payment): void
    {
        $status = $payment->status ?? 'pending';
        $title = match ($status) {
            'verified' => 'Payment confirmed', 'rejected' => 'Payment rejected', default => 'Payment awaiting verification'
        };
        $body = $payment->reference.' · ₱'.$payment->amount.'. '.($status === 'rejected' ? $payment->remarks : ($status === 'verified' ? 'Your payment has been applied to your bill.' : 'The provider will review this payment before updating the bill balance.'));
        $this->notices->send($payment->consumer, 'payment:'.$payment->id.':'.$status, $title, $body, route('portal.section', ['section' => 'payments'], false));
    }
}
