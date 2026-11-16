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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['open', 'under_review', 'referred_to_provider', 'waiting_for_consumer', 'resolved', 'closed'])],
            'type' => ['nullable', Rule::in(['billing', 'payment', 'account', 'service', 'provider', 'other'])],
            'ticket' => ['nullable', 'integer'],
        ]);
        $search = trim($request->string('search')->toString());
        $ticketId = ltrim($search, '#');
        $query = SupportTicket::with(['consumer', 'waterProvider'])
            ->withExists(['messages as has_internal_notes' => fn ($query) => $query->where('internal', true)])
            ->whereNotNull('escalated_at')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('subject', 'like', '%'.$search.'%')
                ->orWhereHas('consumer', fn ($query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('account_number', 'like', '%'.$search.'%'))
                ->orWhereHas('waterProvider', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
                ->when(ctype_digit($ticketId), fn ($query) => $query->orWhere('id', $ticketId))))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')));
        $tickets = (clone $query)->latest('escalated_at')->latest('id')->paginate(20)->withPath(route('admin.tickets.index'))->appends($request->except('ticket'));
        $selectedTicket = $request->filled('ticket')
            ? (clone $query)->whereKey($request->integer('ticket'))->firstOrFail()
            : $tickets->first();
        $selectedTicket?->load(['messages' => fn ($query) => $query->oldest('id')->with('author')]);
        $admins = User::where('role', UserRole::SuperAdmin->value)->where('is_active', true)->orderBy('name')->get();

        return view('admin.tickets', compact('tickets', 'selectedTicket', 'admins'));
    }

    public function show(Request $request, SupportTicket $ticket): View
    {
        abort_if($ticket->escalated_at === null, 404);

        $request->merge(['ticket' => $ticket->id]);

        return $this->index($request);
    }

    public function update(Request $request, SupportTicket $ticket, AuditLogger $audit): RedirectResponse
    {
        abort_if($ticket->escalated_at === null, 404);
        $data = $request->validateWithBag('edit-ticket-review-'.$ticket->id, [
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

        return redirect()->route('admin.tickets.index', ['ticket' => $ticket->id])->with('status', 'Ticket updated.');
    }

    public function reply(Request $request, SupportTicket $ticket, AuditLogger $audit): RedirectResponse
    {
        abort_if($ticket->escalated_at === null, 404);
        abort_if($ticket->status === 'closed', 422);
        $data = $request->validateWithBag('reply-to-ticket-'.$ticket->id, [
            'body' => ['required', 'string', 'max:10000'],
            'internal' => ['nullable', 'boolean'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
        DB::transaction(function () use ($request, $ticket, $data, $audit): void {
            $ticket = SupportTicket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            abort_if($ticket->escalated_at === null, 404);
            abort_if($ticket->status === 'closed', 422);
            $message = $ticket->messages()->create([
                'author_id' => $request->user()->id,
                'body' => $data['body'],
                'internal' => $data['internal'] ?? false,
                'attachment_path' => $request->file('attachment')?->store('ticket-attachments'),
                'attachment_name' => $request->file('attachment')?->getClientOriginalName(),
            ]);
            $audit->record($message, 'ticket.message_added', $request->user(), after: ['ticket_id' => $ticket->id, 'internal' => $message->internal]);
        });

        return back()->with('status', 'Response added.');
    }

    public function attachment(TicketMessage $message): StreamedResponse
    {
        abort_if($message->supportTicket?->escalated_at === null || $message->attachment_path === null, 404);

        return Storage::disk('local')->download($message->attachment_path, $message->attachment_name);
    }
}
