<x-layouts::app :title="$ticket->subject">
    <div class="client-page w-full min-w-0 space-y-6">
        <x-page-header title="Support Requests & Inquiries" description="Contact your water utility team directly." :back-href="route('portal.section', 'support')" :back-label="__('Your support history')" />
        <article class="space-y-4 rounded-xl border border-zinc-200 bg-white p-4 sm:p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <header class="space-y-2 border-b border-zinc-200 pb-4 dark:border-zinc-700 dark:divide-zinc-700">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-medium tabular-nums text-brand-ocean dark:text-sky-200">#{{ $ticket->id }}</span>
                        <flux:badge size="sm">{{ ucfirst($ticket->type) }}</flux:badge>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-ticket-status :status="$ticket->status" />
                        <x-ticket-close :ticket="$ticket" />
                    </div>
                </div>
                <flux:heading level="2" size="lg">{{ $ticket->subject }}</flux:heading>
            </header>
            <x-ticket-conversation :ticket="$ticket" />
            <x-ticket-reply :ticket="$ticket" />
        </article>
    </div>
</x-layouts::app>
