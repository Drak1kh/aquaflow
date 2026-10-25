<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AuditLogger;
use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\User;
use App\UserRole;
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
        $tickets = SupportTicket::with(['consumer', 'waterProvider'])
            ->whereNotNull('escalated_at')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->latest('escalated_at')->paginate(20)->withQueryString();

        return view('admin.tickets', compact('tickets'));
    }

    public function show(SupportTicket $ticket): View
    {
        abort_if($ticket->escalated_at === null, 404);

        return view('admin.ticket', [
            'ticket' => $ticket->load(['consumer', 'waterProvider', 'messages']),
            'admins' => User::where('role', UserRole::SuperAdmin->value)->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, SupportTicket $ticket, AuditLogger $audit): RedirectResponse
    {
        abort_if($ticket->escalated_at === null, 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'under_review', 'referred_to_provider', 'waiting_for_consumer', 'resolved', 'closed'])],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('role', UserRole::SuperAdmin->value)->where('is_active', true)],
            'resolution' => [Rule::requiredIf(in_array($request->input('status'), ['resolved', 'closed'], true)), 'nullable', 'string', 'max:5000'],
        ]);
        $before = $ticket->only(['status', 'assigned_to', 'resolution']);
        $ticket->update([
            ...$data,
            'resolved_at' => in_array($data['status'], ['resolved', 'closed'], true) ? now() : null,
        ]);
        $audit->record($ticket, 'ticket.updated', $request->user(), $before, $data);

        return back()->with('status', 'Ticket updated.');
    }

    public function reply(Request $request, SupportTicket $ticket, AuditLogger $audit): RedirectResponse
    {
        abort_if($ticket->escalated_at === null, 404);
        abort_if($ticket->status === 'closed', 422);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'internal' => ['nullable', 'boolean'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
        $message = $ticket->messages()->create([
            'author_id' => $request->user()->id,
            'body' => $data['body'],
            'internal' => $data['internal'] ?? false,
            'attachment_path' => $request->file('attachment')?->store('ticket-attachments'),
            'attachment_name' => $request->file('attachment')?->getClientOriginalName(),
        ]);
        $audit->record($message, 'ticket.message_added', $request->user(), after: ['ticket_id' => $ticket->id, 'internal' => $message->internal]);

        return back()->with('status', 'Response added.');
    }

    public function attachment(TicketMessage $message): StreamedResponse
    {
        abort_if($message->supportTicket?->escalated_at === null || $message->attachment_path === null, 404);

        return Storage::disk('local')->download($message->attachment_path, $message->attachment_name);
    }
}
