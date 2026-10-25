<?php

namespace App\Http\Controllers;

use App\ActiveProvider;
use App\AuditRecorder;
use App\Models\Consumer;
use App\Models\SupportTicket;
use App\Models\SystemSetting;
use App\Models\TicketAttachment;
use App\Models\TicketMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SupportController extends Controller
{
    public function consumerIndex(Request $request): View
    {
        $consumer = Consumer::where('user_id', $request->user()->id)->firstOrFail();

        return view('pages.support', [
            'mode' => 'consumer',
            'supportEmail' => SystemSetting::setting('support_email', ''),
            'tickets' => SupportTicket::where('consumer_id', $consumer->id)->with(['messages.author', 'attachments'])->latest()->get(),
        ]);
    }

    public function providerIndex(Request $request, ActiveProvider $active): View
    {
        $provider = $active->authorize($request, 'support.manage');

        return view('pages.support', [
            'mode' => 'provider',
            'tickets' => SupportTicket::where('water_provider_id', $provider->id)->with(['consumer', 'messages.author', 'attachments'])->latest()->get(),
        ]);
    }

    public function adminIndex(Request $request): View
    {
        abort_unless($request->user()->is_super_admin, 403);

        return view('pages.support', [
            'mode' => 'admin',
            'tickets' => SupportTicket::whereNotNull('escalated_at')->with(['consumer', 'messages.author', 'attachments'])->latest()->get(),
        ]);
    }

    public function store(Request $request, AuditRecorder $audit): RedirectResponse
    {
        $consumer = Consumer::where('user_id', $request->user()->id)->where('status', 'active')->firstOrFail();
        $data = $request->validate([
            'type' => ['required', Rule::in(['billing', 'payment', 'account', 'service', 'provider', 'other'])],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);
        $ticket = DB::transaction(function () use ($consumer, $request, $data): SupportTicket {
            $ticket = SupportTicket::create([
                'water_provider_id' => $consumer->water_provider_id,
                'consumer_id' => $consumer->id,
                'type' => $data['type'],
                'subject' => $data['subject'],
            ]);
            TicketMessage::create(['support_ticket_id' => $ticket->id, 'author_id' => $request->user()->id, 'body' => $data['body']]);
            $this->saveAttachment($request, $ticket, false);

            return $ticket;
        });
        $audit->record($ticket, 'ticket.created');

        return back()->with('status', 'Support ticket submitted.');
    }

    public function reply(Request $request, SupportTicket $ticket, ActiveProvider $active, AuditRecorder $audit): RedirectResponse
    {
        $provider = $active->authorize($request, 'support.manage');
        abort_unless($ticket->water_provider_id === $provider->id, 404);

        return $this->recordReply($request, $ticket, $audit);
    }

    public function adminReply(Request $request, SupportTicket $ticket, AuditRecorder $audit): RedirectResponse
    {
        abort_unless($request->user()->is_super_admin && $ticket->escalated_at !== null, 404);

        return $this->recordReply($request, $ticket, $audit);
    }

    private function recordReply(Request $request, SupportTicket $ticket, AuditRecorder $audit): RedirectResponse
    {
        abort_if(in_array($ticket->status, ['resolved', 'closed'], true), 422);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'internal' => ['nullable', 'boolean'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);
        $internal = (bool) ($data['internal'] ?? false);
        DB::transaction(function () use ($request, $ticket, $data, $internal): void {
            TicketMessage::create([
                'support_ticket_id' => $ticket->id,
                'author_id' => $request->user()->id,
                'body' => $data['body'],
                'internal' => $internal,
            ]);
            $ticket->update(['status' => $internal ? 'under_review' : 'waiting_for_consumer']);
            $this->saveAttachment($request, $ticket, $internal);
        });
        $audit->record($ticket, 'ticket.replied');
        if (! $internal) {
            $ticket->consumer->notifyAccount('support', 'Support ticket update', 'A response is available for '.$ticket->subject.'.');
        }

        return back()->with('status', 'Response recorded.');
    }

    public function escalate(Request $request, SupportTicket $ticket, ActiveProvider $active, AuditRecorder $audit): RedirectResponse
    {
        $provider = $active->authorize($request, 'support.manage');
        abort_unless($ticket->water_provider_id === $provider->id, 404);
        abort_if(in_array($ticket->status, ['resolved', 'closed'], true), 422);
        $ticket->update(['status' => 'escalated', 'escalated_at' => now()]);
        $audit->record($ticket, 'ticket.escalated');

        return back()->with('status', 'Ticket escalated.');
    }

    public function resolve(Request $request, SupportTicket $ticket, ActiveProvider $active, AuditRecorder $audit): RedirectResponse
    {
        if (! $request->user()->is_super_admin) {
            $provider = $active->authorize($request, 'support.manage');
            abort_unless($ticket->water_provider_id === $provider->id, 404);
        } else {
            abort_unless($ticket->escalated_at !== null, 404);
        }
        $data = $request->validate(['resolution' => ['required', 'string', 'max:5000']]);
        $ticket->update(['status' => 'resolved', 'resolution' => $data['resolution']]);
        $audit->record($ticket, 'ticket.resolved');
        $ticket->consumer->notifyAccount('support', 'Support ticket resolved', $ticket->subject.' has been resolved.');

        return back()->with('status', 'Ticket resolved.');
    }

    public function attachment(Request $request, TicketAttachment $attachment, ActiveProvider $active): StreamedResponse
    {
        $ticket = SupportTicket::whereKey($attachment->support_ticket_id)->firstOrFail();
        $allowed = ($request->user()->is_super_admin && $ticket->escalated_at)
            || (! $attachment->internal && Consumer::where('user_id', $request->user()->id)->whereKey($ticket->consumer_id)->exists());

        if (! $allowed) {
            try {
                $provider = $active->authorize($request, 'support.manage');
                $allowed = $provider->id === $ticket->water_provider_id;
            } catch (HttpException $exception) {
                $allowed = false;
            }
        }
        abort_unless($allowed, 404);

        return Storage::download($attachment->path, $attachment->name);
    }

    private function saveAttachment(Request $request, SupportTicket $ticket, bool $internal): void
    {
        $file = $request->file('attachment');
        if ($file) {
            TicketAttachment::create([
                'support_ticket_id' => $ticket->id,
                'uploaded_by' => $request->user()->id,
                'path' => $file->store('ticket-attachments'),
                'name' => $file->getClientOriginalName(),
                'internal' => $internal,
            ]);
        }
    }
}
