<?php

namespace App\Http\Controllers\Consumer;

use App\Actions\AuditLogger;
use App\Actions\CloseTicket;
use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketController extends Controller
{
    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['billing', 'payment', 'account', 'service', 'provider', 'other'])],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
        ]);
        $consumer = $request->user()->consumer;
        $ticket = SupportTicket::create([
            ...$data, 'consumer_id' => $consumer->id,
            'water_provider_id' => $consumer->water_provider_id, 'opened_by' => $request->user()->id,
        ]);
        $audit->record($ticket, 'ticket.created', $request->user(), after: ['type' => $ticket->type]);

        return back()->with('status', 'Support ticket submitted.');
    }

    public function show(Request $request, SupportTicket $ticket): View
    {
        abort_unless($ticket->consumer_id === $request->user()->consumer_id, 404);

        return view('consumer.ticket', ['ticket' => $ticket->load(['consumer', 'messages' => fn ($query) => $query->where('internal', false)->oldest('id')->with('author')])]);
    }

    public function reply(Request $request, SupportTicket $ticket, AuditLogger $audit): RedirectResponse
    {
        abort_unless($ticket->consumer_id === $request->user()->consumer_id, 404);
        abort_if($ticket->status === 'closed', 422);
        $data = $request->validateWithBag('reply-to-ticket-'.$ticket->id, [
            'body' => ['required', 'string', 'max:10000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
        DB::transaction(function () use ($request, $ticket, $data, $audit): void {
            $ticket = SupportTicket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            abort_unless($ticket->consumer_id === $request->user()->consumer_id, 404);
            abort_if($ticket->status === 'closed', 422);
            $message = $ticket->messages()->create([
                'author_id' => $request->user()->id,
                'body' => $data['body'],
                'attachment_path' => $request->file('attachment')?->store('ticket-attachments'),
                'attachment_name' => $request->file('attachment')?->getClientOriginalName(),
            ]);
            $audit->record($message, 'ticket.message_added', $request->user(), after: ['ticket_id' => $ticket->id]);
        });

        return back()->with('status', 'Reply sent.');
    }

    public function attachment(Request $request, TicketMessage $message): StreamedResponse
    {
        abort_if($message->internal || $message->supportTicket?->consumer_id !== $request->user()->consumer_id || $message->attachment_path === null, 404);

        return Storage::disk('local')->download($message->attachment_path, $message->attachment_name);
    }

    public function close(Request $request, SupportTicket $ticket, CloseTicket $closeTicket): RedirectResponse
    {
        abort_unless($ticket->consumer_id === $request->user()->consumer_id, 404);
        $closeTicket->handle($ticket, $request->user(), $request->input('resolution'));

        return redirect()->route('portal.tickets.show', $ticket)->with('status', 'Ticket closed.');
    }
}
