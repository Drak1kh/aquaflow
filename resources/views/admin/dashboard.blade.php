<x-layouts::app :title="__('Platform dashboard')">
    <div class="flex w-full min-w-0 flex-col gap-8">
        <x-page-header :title="__('Platform dashboard')" :description="__('Live activity across every water provider.')">
            <x-slot:actions>
                <flux:button :href="route('admin.reports.index')" icon="chart-bar">{{ __('Open reports') }}</flux:button>
            </x-slot:actions>
        </x-page-header>

        <div class="grid gap-px overflow-hidden rounded-xl border border-zinc-200 bg-zinc-200 sm:grid-cols-2 xl:grid-cols-4 dark:border-zinc-700 dark:bg-zinc-700">
            @foreach ([
                [__('Providers'), $providers, __(':count active', ['count' => $activeProviders])],
                [__('Consumers'), $consumers, __('Registered accounts')],
                [__('Bills'), $bills, __('Generated')],
                [__('Payments'), $payments, __(':count pending verification', ['count' => $pendingPayments])],
            ] as [$label, $value, $detail])
                <div class="bg-white px-5 py-5 dark:bg-zinc-900">
                    <flux:text>{{ $label }}</flux:text>
                    <div class="mt-2 text-3xl font-semibold tabular-nums text-brand-ink dark:text-white">{{ number_format($value) }}</div>
                    <flux:text class="mt-1 text-sm">{{ $detail }}</flux:text>
                </div>
            @endforeach
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="lg:col-span-2">
                <div class="flex items-center justify-between gap-4">
                    <flux:heading level="2" size="lg">{{ __('Escalated tickets') }} · {{ $openTickets }} {{ __('open') }}</flux:heading>
                    <flux:link :href="route('admin.tickets.index')">{{ __('View all') }}</flux:link>
                </div>
                <div class="mt-4 divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 bg-white dark:divide-zinc-700 dark:border-zinc-700 dark:bg-zinc-900">
                    @forelse ($recentTickets as $ticket)
                        <a href="{{ route('admin.tickets.show', $ticket) }}" class="flex items-center justify-between gap-4 px-5 py-4 hover:bg-brand-surface focus-visible:outline-2 focus-visible:outline-[#0869CF] dark:hover:bg-zinc-800">
                            <span class="min-w-0"><span class="block truncate font-medium">{{ $ticket->subject }}</span><span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $ticket->waterProvider->name }} · {{ $ticket->consumer->name }}</span></span>
                            <flux:badge color="blue">{{ str_replace('_', ' ', $ticket->status) }}</flux:badge>
                        </a>
                    @empty
                        <div class="px-5 py-9 text-center text-sm text-zinc-600 dark:text-zinc-300">{{ __('No escalated tickets need review.') }}</div>
                    @endforelse
                </div>
            </section>
            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading level="2" size="lg">{{ __('Financial position') }}</flux:heading>
                <dl class="mt-5 divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ([[__('Total billed'), $billed], [__('Verified collections'), $collected], [__('Outstanding'), $outstanding]] as [$label, $amount])
                        <div class="flex items-center justify-between gap-4 py-3"><dt class="text-sm text-zinc-600 dark:text-zinc-300">{{ $label }}</dt><dd class="font-semibold tabular-nums">₱{{ number_format((float) $amount, 2) }}</dd></div>
                    @endforeach
                </dl>
            </section>
        </div>

        <section>
            <flux:heading level="2" size="lg">{{ __('Recent system activity') }}</flux:heading>
            <div class="mt-4 divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 bg-white dark:divide-zinc-700 dark:border-zinc-700 dark:bg-zinc-900">
                @forelse ($activity as $event)
                    <div class="flex justify-between gap-4 px-5 py-3 text-sm"><span>{{ str_replace('.', ' · ', $event->action) }} <span class="text-zinc-500">#{{ $event->subject_id }}</span></span><time class="shrink-0 text-zinc-500">{{ $event->created_at->diffForHumans() }}</time></div>
                @empty
                    <div class="px-5 py-8 text-center text-sm text-zinc-600 dark:text-zinc-300">{{ __('Activity will appear here when records change.') }}</div>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts::app>
