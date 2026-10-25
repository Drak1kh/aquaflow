<x-layouts::app :title="__('Admin dashboard')">
    <main class="w-full min-w-0 space-y-8 px-4 py-8 text-[#062E68] sm:px-6 lg:px-8 dark:text-white">
        <header class="flex flex-col gap-5 rounded-2xl bg-[#063E91] px-6 py-7 text-white sm:flex-row sm:items-end sm:justify-between sm:px-8 sm:py-8 dark:bg-[#062E68]">
            <div class="space-y-2">
                <flux:heading size="xl" class="text-white">Admin dashboard</flux:heading>
                <p class="max-w-2xl text-sm leading-6 text-[#D8F1F9]">A current view of providers, accounts, and activity across AquaFlow.</p>
            </div>
            <flux:button :href="route('admin.overview')" wire:navigate>View platform oversight</flux:button>
        </header>

        @include('partials.aquaflow-alerts')

        <section aria-label="Platform totals" class="grid grid-cols-2 gap-x-6 gap-y-6 border-y border-[#BDECF8] py-6 sm:grid-cols-4 dark:border-slate-700">
            <div class="space-y-1"><p class="text-sm text-slate-500 dark:text-slate-400">Providers</p><p class="text-3xl font-semibold tabular-nums">{{ $providerCount }}</p><p class="text-xs text-slate-500 dark:text-slate-400">{{ $activeProviderCount }} active</p></div>
            <div class="space-y-1"><p class="text-sm text-slate-500 dark:text-slate-400">User accounts</p><p class="text-3xl font-semibold tabular-nums">{{ $userCount }}</p><p class="text-xs text-slate-500 dark:text-slate-400">{{ $activeUserCount }} active</p></div>
            <div class="space-y-1"><p class="text-sm text-slate-500 dark:text-slate-400">Consumers</p><p class="text-3xl font-semibold tabular-nums">{{ $consumerCount }}</p><p class="text-xs text-slate-500 dark:text-slate-400">Across all providers</p></div>
            <div class="space-y-1"><p class="text-sm text-slate-500 dark:text-slate-400">Bills generated</p><p class="text-3xl font-semibold tabular-nums">{{ $billCount }}</p><p class="text-xs text-slate-500 dark:text-slate-400">Excludes void bills</p></div>
        </section>

        <div class="grid gap-8 xl:grid-cols-[minmax(0,1.7fr)_minmax(19rem,1fr)]">
            <div class="min-w-0 space-y-8">
                <section aria-labelledby="recent-providers-heading" class="space-y-4">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div><flux:heading id="recent-providers-heading" size="lg">Recently added providers</flux:heading><flux:text>Organization status and consumer totals.</flux:text></div>
                        <flux:button :href="route('admin.providers.index')" size="sm" wire:navigate>View all providers</flux:button>
                    </div>
                    <div class="overflow-x-auto rounded-2xl border border-[#BDECF8] bg-white dark:border-slate-700 dark:bg-slate-900">
                        <flux:table>
                            <flux:table.columns><flux:table.column>Provider</flux:table.column><flux:table.column>Consumers</flux:table.column><flux:table.column>Status</flux:table.column></flux:table.columns>
                            <flux:table.rows>
                                @forelse ($latestProviders as $provider)
                                    <flux:table.row>
                                        <flux:table.cell><p class="font-semibold text-[#062E68] dark:text-white">{{ $provider->name }}</p><p class="text-xs text-slate-500 dark:text-slate-400">{{ $provider->code }}</p></flux:table.cell>
                                        <flux:table.cell class="tabular-nums">{{ $provider->consumers_count }}</flux:table.cell>
                                        <flux:table.cell><flux:badge :color="$provider->status === 'active' ? 'green' : 'zinc'" size="sm">{{ ucfirst($provider->status) }}</flux:badge></flux:table.cell>
                                    </flux:table.row>
                                @empty
                                    <flux:table.row><flux:table.cell colspan="3" class="py-10 text-center">No providers yet. Add one to create a workspace.</flux:table.cell></flux:table.row>
                                @endforelse
                            </flux:table.rows>
                        </flux:table>
                    </div>
                </section>

                <section aria-labelledby="recent-activity-heading" class="space-y-4">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div><flux:heading id="recent-activity-heading" size="lg">Recent activity</flux:heading><flux:text>Latest recorded changes across the platform.</flux:text></div>
                        <flux:button :href="route('admin.overview')" size="sm" wire:navigate>Open activity log</flux:button>
                    </div>
                    <div class="divide-y divide-[#BDECF8] rounded-2xl border border-[#BDECF8] bg-white px-5 dark:divide-slate-700 dark:border-slate-700 dark:bg-slate-900">
                        @forelse ($recentAudits as $audit)
                            <div class="flex flex-wrap items-center justify-between gap-2 py-4 text-sm">
                                <div><p class="font-medium text-[#062E68] dark:text-white">{{ ucwords(str_replace('.', ' ', $audit->action)) }}</p><p class="text-xs text-slate-500 dark:text-slate-400">{{ $audit->water_provider_id ? 'Provider #'.$audit->water_provider_id : 'Platform' }}</p></div>
                                <time class="text-xs text-slate-500 dark:text-slate-400" datetime="{{ $audit->created_at?->toIso8601String() }}">{{ $audit->created_at?->diffForHumans() }}</time>
                            </div>
                        @empty
                            <p class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">No recorded activity yet.</p>
                        @endforelse
                    </div>
                </section>
            </div>

            <aside class="space-y-8">
                <section aria-labelledby="access-heading" class="rounded-2xl bg-[#EAF8FC] p-6 text-[#062E68] dark:bg-slate-800 dark:text-white">
                    <flux:heading id="access-heading" size="lg">Manage access</flux:heading>
                    <p class="mt-1 text-sm leading-6 text-[#31547E] dark:text-slate-300">Keep accounts and provider roles current.</p>
                    <div class="mt-5 divide-y divide-[#BDECF8] dark:divide-slate-700">
                        <a href="{{ route('admin.users.index') }}" wire:navigate class="flex items-center justify-between gap-3 py-4 text-sm font-semibold text-[#063E91] underline-offset-4 hover:underline focus-visible:rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0869CF] dark:text-[#BDECF8]"><span>Users</span><span class="tabular-nums">{{ $userCount }}</span></a>
                        <a href="{{ route('admin.roles.index') }}" wire:navigate class="flex items-center justify-between gap-3 py-4 text-sm font-semibold text-[#063E91] underline-offset-4 hover:underline focus-visible:rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0869CF] dark:text-[#BDECF8]"><span>Roles &amp; permissions</span><span class="tabular-nums">{{ $roleCount }}</span></a>
                        <a href="{{ route('admin.providers.index') }}" wire:navigate class="flex items-center justify-between gap-3 py-4 text-sm font-semibold text-[#063E91] underline-offset-4 hover:underline focus-visible:rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0869CF] dark:text-[#BDECF8]"><span>Providers</span><span class="tabular-nums">{{ $providerCount }}</span></a>
                    </div>
                </section>

                <section aria-labelledby="support-heading" class="space-y-3 border-t border-[#BDECF8] pt-6 dark:border-slate-700">
                    <flux:heading id="support-heading" size="lg">Escalated support</flux:heading>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Escalated tickets that are still open.</p>
                    <p class="text-3xl font-semibold tabular-nums">{{ $escalatedTicketCount }}</p>
                    <flux:button :href="route('admin.support')" size="sm" wire:navigate>Review tickets</flux:button>
                </section>
            </aside>
        </div>
    </main>
</x-layouts::app>
