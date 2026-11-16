@props(['tickets', 'selectedTicket', 'admin' => false, 'admins' => null])

@php($indexRoute = $admin ? 'admin.tickets.index' : 'provider.tickets.index')

        <div class="grid items-start gap-5 lg:grid-cols-[minmax(16rem,0.8fr)_minmax(0,1.2fr)]">
            <section class="min-w-0 overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900" aria-labelledby="ticket-queue-heading">
                <div class="border-b border-zinc-200 bg-brand-surface px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800/50">
                    <flux:heading id="ticket-queue-heading" level="2" class="text-sm">{{ __('Tickets queue') }} <span class="font-normal text-zinc-500 dark:text-zinc-400">({{ $tickets->total() }})</span></flux:heading>
                </div>
                <nav aria-label="{{ __('Support tickets') }}" class="max-h-80 divide-y divide-zinc-100 overflow-y-auto lg:max-h-[36rem] dark:divide-zinc-800">
                    @forelse ($tickets as $ticket)
                        <a href="{{ route($indexRoute, [...request()->only('search', 'status', 'type', 'page'), 'ticket' => $ticket->id]) }}#ticket-detail" @if ($selectedTicket?->is($ticket)) aria-current="true" @endif @class([
                            'block border-s-3 p-4 transition-colors motion-reduce:transition-none',
                            'border-brand-aqua bg-cyan-50 dark:bg-cyan-950/40' => $selectedTicket?->is($ticket),
                            'border-transparent hover:bg-brand-surface dark:hover:bg-zinc-800' => !$selectedTicket?->is($ticket),
                        ])>
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="text-xs font-medium tabular-nums text-brand-ocean dark:text-sky-200">#{{ $ticket->id }}</span>
                                <x-ticket-status :status="$ticket->status" />
                            </div>
                            <p class="mt-2 text-sm font-semibold leading-relaxed text-brand-ink dark:text-zinc-100">{{ $ticket->subject }}</p>
                            <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                                <span>{{ $ticket->consumer->name }}</span>
                                <time datetime="{{ $ticket->created_at->toDateString() }}">{{ $ticket->created_at->format(app(\App\Actions\PlatformSettings::class)->get('date_format')) }}</time>
                            </div>
                            <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ ucfirst($ticket->type) }}</p>
                            @if ($admin)
                                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $ticket->waterProvider->name }}</p>
                                @if ($ticket->has_internal_notes)
                                    <flux:badge size="sm" color="amber" class="mt-2">{{ __('Has internal notes') }}</flux:badge>
                                @endif
                            @endif
                        </a>
                    @empty
                        <p class="p-6 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No tickets match your search.') }}</p>
                    @endforelse
                </nav>
                @if ($tickets->hasPages())
                    <div class="border-t border-zinc-200 p-3 dark:border-zinc-700">{{ $tickets->onEachSide(0)->links('pagination::simple-tailwind') }}</div>
                @endif
            </section>

            @if ($selectedTicket)
                <article id="ticket-detail" class="min-w-0 scroll-mt-6 space-y-5 rounded-xl border border-zinc-200 bg-white p-4 sm:p-5 dark:border-zinc-700 dark:bg-zinc-900" aria-labelledby="ticket-subject">
                    <header class="space-y-3 border-b border-zinc-200 pb-4 dark:border-zinc-700">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-2 text-xs text-brand-ocean dark:text-sky-200">
                                <span class="font-medium tabular-nums">#{{ $selectedTicket->id }}</span>
                                <span aria-hidden="true">·</span>
                                <span>{{ ucfirst($selectedTicket->type) }}</span>
                            </div>
                            <x-ticket-status :status="$selectedTicket->status" />
                        </div>
                        <flux:heading id="ticket-subject" level="2" size="lg">{{ $selectedTicket->subject }}</flux:heading>
                        <div class="flex flex-wrap gap-x-5 gap-y-2 text-xs text-zinc-500 dark:text-zinc-400">
                            <p>{{ __('Customer:') }} <span class="font-medium text-brand-ink dark:text-zinc-200">{{ $selectedTicket->consumer->name }}</span></p>
                            <p>{{ __('Account:') }} <span class="font-medium text-brand-ink dark:text-zinc-200">{{ $selectedTicket->consumer->account_number }}</span></p>
                        </div>
                        @if ($admin)
                            <dl class="flex flex-wrap gap-x-5 gap-y-2 text-xs">
                                <div><dt class="inline text-zinc-500 dark:text-zinc-400">{{ __('Provider:') }}</dt> <dd class="inline font-medium">{{ $selectedTicket->waterProvider->name }}</dd></div>
                                <div><dt class="inline text-zinc-500 dark:text-zinc-400">{{ __('Assigned administrator:') }}</dt> <dd class="inline font-medium">{{ $admins->firstWhere('id', $selectedTicket->assigned_to)?->name ?? __('Unassigned') }}</dd></div>
                            </dl>
                            <x-ticket-review :ticket="$selectedTicket" :admins="$admins" />
                        @elseif ($selectedTicket->escalated_at)
                            <flux:badge color="blue" size="sm" icon="arrow-up-right">{{ __('Escalated to Super Admin') }}</flux:badge>
                        @elseif ($selectedTicket->status !== 'closed')
                            @can('tickets.respond')
                                <form method="POST" action="{{ route('provider.tickets.escalate', $selectedTicket) }}">
                                    @csrf
                                    <flux:button type="submit" size="sm" icon="arrow-up-right" color="rose">{{ __('Escalate') }}</flux:button>
                                </form>
                            @endcan
                        @endif
                        @if (!$admin)
                            <x-ticket-close :ticket="$selectedTicket" :provider="true" />
                        @endif
                    </header>
                    @if ($selectedTicket->resolution)
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm dark:border-emerald-800 dark:bg-emerald-950/30">
                            <p class="font-semibold">{{ __('Resolution') }}</p>
                            <p class="mt-1 whitespace-pre-wrap">{{ $selectedTicket->resolution }}</p>
                        </div>
                    @endif
                    <x-ticket-conversation :ticket="$selectedTicket" :provider="true" :admin="$admin" />
                    <x-ticket-reply :ticket="$selectedTicket" :provider="true" :admin="$admin" />
                </article>
            @else
                <div class="rounded-xl border border-dashed border-zinc-300 bg-brand-surface px-6 py-16 text-center dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:icon.chat-bubble-left-right class="mx-auto mb-3 size-8 text-zinc-400" />
                    <flux:heading level="2">{{ __('Your conversations will appear here') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('Choose a ticket from the queue to review and reply.') }}</flux:text>
                </div>
            @endif
        </div>
