<x-layouts::app :title="__('Help and support')">
    <main class="mx-auto flex w-full max-w-5xl flex-col gap-8 p-6 text-[#062E68] dark:text-white">
        <header><flux:heading size="xl">{{ $mode === 'admin' ? 'Escalated support' : 'Help and support' }}</flux:heading><flux:text>Track concerns and responses</flux:text></header>
        @include('partials.aquaflow-alerts')

        @if ($mode === 'consumer')
            <flux:card class="space-y-4"><flux:heading>Contact your provider</flux:heading>
                @if ($supportEmail)<flux:text>Platform support: <a class="text-[#079EE9] underline" href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a></flux:text>@endif
                <form method="POST" action="{{ route('consumer.support.store') }}" enctype="multipart/form-data" class="space-y-4">@csrf
                    <flux:select name="type" label="Concern" required><flux:select.option value="billing">Billing</flux:select.option><flux:select.option value="payment">Payment</flux:select.option><flux:select.option value="account">Account</flux:select.option><flux:select.option value="service">Service</flux:select.option><flux:select.option value="provider">Provider</flux:select.option><flux:select.option value="other">Other</flux:select.option></flux:select>
                    <flux:input name="subject" label="Subject" required />
                    <flux:textarea name="body" label="What happened?" required />
                    <flux:input name="attachment" label="Attachment (optional)" type="file" accept=".jpg,.jpeg,.png,.pdf" />
                    <flux:button type="submit" variant="primary">Submit ticket</flux:button>
                </form>
            </flux:card>
        @endif

        <div class="space-y-5">
            @forelse ($tickets as $ticket)
                <flux:card class="space-y-4">
                    <div class="flex flex-wrap items-start justify-between gap-3"><div><flux:heading>#{{ $ticket->id }} · {{ $ticket->subject }}</flux:heading><flux:text>{{ ucfirst($ticket->type) }} @if ($mode !== 'consumer') · {{ $ticket->consumer->name }} @endif</flux:text></div><flux:badge>{{ str_replace('_', ' ', $ticket->status) }}</flux:badge></div>
                    <div class="space-y-3">
                        @foreach ($ticket->messages as $message)
                            @if ($mode !== 'consumer' || ! $message->internal)
                                <div class="rounded-xl border border-[#BDECF8] p-3 dark:border-zinc-700"><strong>{{ $message->author->name }}</strong> <span class="text-xs">{{ $message->created_at->diffForHumans() }} @if ($message->internal) · Internal note @endif</span><p class="whitespace-pre-wrap text-sm">{{ $message->body }}</p></div>
                            @endif
                        @endforeach
                    </div>
                    @foreach ($ticket->attachments as $attachment)
                        @if ($mode !== 'consumer' || ! $attachment->internal)
                            <a class="text-sm text-[#079EE9] underline" href="{{ route('support.attachments.show', $attachment) }}">Attachment: {{ $attachment->name }}</a>
                        @endif
                    @endforeach
                    @if ($ticket->resolution)<flux:callout variant="success">Resolution: {{ $ticket->resolution }}</flux:callout>@endif

                    @if ($mode !== 'consumer' && ! in_array($ticket->status, ['resolved', 'closed'], true))
                        <form method="POST" action="{{ $mode === 'admin' ? route('admin.support.reply', $ticket) : route('provider.support.reply', $ticket) }}" enctype="multipart/form-data" class="space-y-3 border-t border-[#BDECF8] pt-4 dark:border-zinc-700">@csrf
                            <flux:textarea name="body" label="Response" required />
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="internal" value="1"> Internal note</label>
                            <flux:input name="attachment" label="Attachment (optional)" type="file" accept=".jpg,.jpeg,.png,.pdf" />
                            <flux:button type="submit" variant="primary">Send response</flux:button>
                        </form>
                        <div class="flex flex-wrap items-end gap-3">
                            @if ($mode === 'provider' && ! $ticket->escalated_at)
                                <form method="POST" action="{{ route('provider.support.escalate', $ticket) }}">@csrf<flux:button type="submit">Escalate to platform</flux:button></form>
                            @endif
                            <form method="POST" action="{{ $mode === 'admin' ? route('admin.support.resolve', $ticket) : route('provider.support.resolve', $ticket) }}" class="flex flex-wrap items-end gap-2">@csrf<flux:input name="resolution" label="Resolution" required /><flux:button type="submit">Resolve</flux:button></form>
                        </div>
                    @endif
                </flux:card>
            @empty
                <flux:card><flux:text>No tickets yet.</flux:text></flux:card>
            @endforelse
        </div>
    </main>
</x-layouts::app>
