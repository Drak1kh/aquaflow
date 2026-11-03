<x-layouts::app title="Support Tickets">
<div class="provider-page w-full min-w-0 space-y-6">
<x-page-header title="Support Tickets" description="Respond to consumer concerns and escalate disputes that require Super Admin review." />
@forelse ($tickets as $ticket)
    <article class="space-y-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:heading level="2">{{ $ticket->subject }} · {{ $ticket->consumer->name }}</flux:heading>
        <flux:text>{{ ucfirst(str_replace('_', ' ', $ticket->status)) }} · {{ $ticket->escalated_at ? 'Escalated to Super Admin' : 'Provider review' }}</flux:text>
        <flux:text>{{ $ticket->description }}</flux:text>
        @foreach ($ticket->messages as $message)
            <flux:text>{{ $message->body }} @if ($message->attachment_path)<a href="{{ route('provider.tickets.attachment', $message) }}">Download attachment</a>@endif</flux:text>
        @endforeach
        @can('tickets.respond')
            @if (!$ticket->escalated_at && $ticket->status !== 'closed')
                <form method="POST" action="{{ route('provider.tickets.escalate', $ticket) }}">@csrf<flux:button type="submit">Escalate for administrative review</flux:button></form>
            @endif
            @if ($ticket->status !== 'closed')
                <form method="POST" action="{{ route('provider.tickets.reply', $ticket) }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <flux:textarea name="body" label="Response" placeholder="Explain the next step or resolution" required />
                    <flux:input name="attachment" type="file" label="Attachment (PDF or image, up to 5 MB)" accept=".pdf,.jpg,.jpeg,.png" />
                    <flux:button type="submit">Send response</flux:button>
                </form>
            @endif
        @endcan
    </article>
@empty <flux:text>No support tickets have been submitted.</flux:text> @endforelse
{{ $tickets->links() }}
</div>
</x-layouts::app>
