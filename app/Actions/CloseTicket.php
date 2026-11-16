<?php

namespace App\Actions;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CloseTicket
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(SupportTicket $ticket, User $actor, mixed $resolution): void
    {
        DB::transaction(function () use ($ticket, $actor, $resolution): void {
            $ticket = SupportTicket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            if ($ticket->status === 'closed') {
                return;
            }

            $data = Validator::make(['resolution' => $resolution], [
                'resolution' => ['required', 'string', 'max:5000'],
            ])->validateWithBag('close-ticket-'.$ticket->id);
            $before = $ticket->only(['status', 'resolution']);
            $ticket->update(['status' => 'closed', 'resolution' => $data['resolution'], 'resolved_at' => $ticket->resolved_at ?? now()]);
            $this->audit->record($ticket, 'ticket.updated', $actor, $before, $ticket->only(['status', 'resolution']));
        });
    }
}
