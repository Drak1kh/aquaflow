<?php

namespace App\Http\Controllers\Consumer;

use App\Actions\AuditLogger;
use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketController extends Controller
{
    public function index(Request $request): View
    {
        $consumerId = $request->user()->consumer_id;

        $bills = $request->user()->can('billing.view') ? Bill::with('allocations')->where('consumer_id', $consumerId)->where('status', '!=', 'void')->latest('period_month')->get() : collect();
        $latestReading = MeterReading::whereHas('connection', fn ($q) => $q->where('consumer_id', $consumerId))->latest('period_month')->first();
        $nextBill = $bills->filter(fn ($bill) => bccomp($bill->balance(), '0', 2) > 0)->sortBy('due_at')->first();

        return view('consumer.portal', [
            'currentBill' => $bills->first(fn ($bill) => $bill->period_month->format('Y-m') === now()->format('Y-m')),
            'latestReading' => $latestReading,
            'nextBill' => $nextBill,
            'consumer' => $request->user()->consumer,
            'bills' => $bills,
            'payments' => $request->user()->can('payments.view') ? Payment::where('consumer_id', $consumerId)->latest('id')->get() : collect(),
            'tickets' => SupportTicket::where('consumer_id', $consumerId)->latest('id')->get(),
        ]);
    }

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

        return view('consumer.ticket', ['ticket' => $ticket->load(['messages' => fn ($query) => $query->where('internal', false)])]);
    }

    public function reply(Request $request, SupportTicket $ticket, AuditLogger $audit): RedirectResponse
    {
        abort_unless($ticket->consumer_id === $request->user()->consumer_id, 404);
        abort_if($ticket->status === 'closed', 422);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
        $message = $ticket->messages()->create([
            'author_id' => $request->user()->id,
            'body' => $data['body'],
            'attachment_path' => $request->file('attachment')?->store('ticket-attachments'),
            'attachment_name' => $request->file('attachment')?->getClientOriginalName(),
        ]);
        $audit->record($message, 'ticket.message_added', $request->user(), after: ['ticket_id' => $ticket->id]);

        return back()->with('status', 'Reply sent.');
    }

    public function attachment(Request $request, TicketMessage $message): StreamedResponse
    {
        abort_if($message->internal || $message->supportTicket?->consumer_id !== $request->user()->consumer_id || $message->attachment_path === null, 404);

        return Storage::disk('local')->download($message->attachment_path, $message->attachment_name);
    }
}
