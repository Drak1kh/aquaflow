<x-layouts::app :title="__('Admin dashboard')">
    <main class="w-full min-w-0 flex-1 bg-[#F7FCFE] py-8 text-[#062E68] lg:py-10 dark:bg-zinc-800 dark:text-slate-100">
        <div class="w-full space-y-9">
            <header class="flex flex-col gap-6 border-b border-[#D5E8F0] pb-8 sm:flex-row sm:items-end sm:justify-between dark:border-zinc-700">
                <div class="space-y-2">
                    <h1 class="text-3xl font-semibold tracking-tight sm:text-[2.125rem]">Admin dashboard</h1>
                    <p class="max-w-xl text-sm leading-6 text-[#527189] dark:text-zinc-400">Monitor your platform, review provider activity, and keep access up to date.</p>
                </div>
                <flux:button :href="route('admin.overview')" variant="primary" icon:trailing="arrow-up-right" wire:navigate>Platform oversight</flux:button>
            </header>

            @include('partials.aquaflow-alerts')

            <section aria-label="Platform totals" class="overflow-hidden rounded-2xl border border-[#D5E8F0] bg-white dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-[#E3EFF4] px-6 py-4 dark:border-zinc-700">
                    <h2 class="text-sm font-semibold">Platform at a glance</h2>
                    <span class="text-xs text-[#527189] dark:text-zinc-400">Current totals across all providers</span>
                </div>
                <div class="grid grid-cols-2 lg:grid-cols-4">
                    <div class="border-b border-r border-[#E3EFF4] px-6 py-6 lg:border-b-0 dark:border-zinc-700">
                        <p class="text-sm font-medium text-[#527189] dark:text-zinc-400">Providers</p>
                        <p class="mt-3 text-3xl font-semibold tracking-tight tabular-nums">{{ number_format($providerCount) }}</p>
                        <p class="mt-2 text-xs text-[#527189] dark:text-zinc-400"><span class="font-semibold text-[#087D8E] dark:text-cyan-300">{{ number_format($activeProviderCount) }} active</span> · {{ number_format($providerCount - $activeProviderCount) }} inactive</p>
                    </div>
                    <div class="border-b border-[#E3EFF4] px-6 py-6 lg:border-b-0 lg:border-r dark:border-zinc-700">
                        <p class="text-sm font-medium text-[#527189] dark:text-zinc-400">User accounts</p>
                        <p class="mt-3 text-3xl font-semibold tracking-tight tabular-nums">{{ number_format($userCount) }}</p>
                        <p class="mt-2 text-xs text-[#527189] dark:text-zinc-400"><span class="font-semibold text-[#087D8E] dark:text-cyan-300">{{ number_format($activeUserCount) }} active</span> across the platform</p>
                    </div>
                    <div class="border-r border-[#E3EFF4] px-6 py-6 dark:border-zinc-700">
                        <p class="text-sm font-medium text-[#527189] dark:text-zinc-400">Consumers</p>
                        <p class="mt-3 text-3xl font-semibold tracking-tight tabular-nums">{{ number_format($consumerCount) }}</p>
                        <p class="mt-2 text-xs text-[#527189] dark:text-zinc-400">Registered with providers</p>
                    </div>
                    <div class="px-6 py-6">
                        <p class="text-sm font-medium text-[#527189] dark:text-zinc-400">Bills generated</p>
                        <p class="mt-3 text-3xl font-semibold tracking-tight tabular-nums">{{ number_format($billCount) }}</p>
                        <p class="mt-2 text-xs text-[#527189] dark:text-zinc-400">Excludes void bills</p>
                    </div>
                </div>
                <div class="grid border-t border-[#E3EFF4] sm:grid-cols-3 dark:border-zinc-700">
                    <div class="border-b border-[#E3EFF4] px-6 py-5 sm:border-b-0 sm:border-r dark:border-zinc-700">
                        <p class="text-sm text-[#527189] dark:text-zinc-400">Total billed</p>
                        <p class="mt-2 text-xl font-semibold tabular-nums">{{ \App\DecimalAmount::format($billedCents) }}</p>
                        <p class="mt-1 text-xs text-[#527189] dark:text-zinc-400">Non-void bills</p>
                    </div>
                    <div class="border-b border-[#E3EFF4] px-6 py-5 sm:border-b-0 sm:border-r dark:border-zinc-700">
                        <p class="text-sm text-[#527189] dark:text-zinc-400">Payments collected</p>
                        <p class="mt-2 text-xl font-semibold tabular-nums">{{ \App\DecimalAmount::format($collectedCents) }}</p>
                        <p class="mt-1 text-xs text-[#527189] dark:text-zinc-400">{{ number_format($paymentCount) }} verified payments</p>
                    </div>
                    <div class="px-6 py-5">
                        <p class="text-sm text-[#527189] dark:text-zinc-400">Outstanding balance</p>
                        <p class="mt-2 text-xl font-semibold tabular-nums">{{ \App\DecimalAmount::format($outstandingCents) }}</p>
                        <p class="mt-1 text-xs text-[#527189] dark:text-zinc-400">Including bill adjustments</p>
                    </div>
                </div>
            </section>

            <div class="grid items-start gap-8 xl:grid-cols-[minmax(0,1fr)_19rem]">
                <div class="min-w-0 space-y-9">
                    <section aria-labelledby="recent-providers-heading">
                        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                            <div><h2 id="recent-providers-heading" class="text-xl font-semibold tracking-tight">Recently added providers</h2><p class="mt-1 text-sm text-[#527189] dark:text-zinc-400">A quick check on new organizations and their status.</p></div>
                            <a href="{{ route('admin.providers.index') }}" wire:navigate class="rounded text-sm font-semibold text-[#0869CF] underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0869CF] dark:text-cyan-300">View all providers <span aria-hidden="true">→</span></a>
                        </div>
                        <div class="overflow-x-auto rounded-2xl border border-[#D5E8F0] bg-white dark:border-zinc-700 dark:bg-zinc-900">
                            <flux:table>
                                <flux:table.columns><flux:table.column>Provider</flux:table.column><flux:table.column>Consumers</flux:table.column><flux:table.column>Status</flux:table.column></flux:table.columns>
                                <flux:table.rows>
                                    @forelse ($latestProviders as $provider)
                                        <flux:table.row>
                                            <flux:table.cell><p class="font-semibold text-[#062E68] dark:text-slate-100">{{ $provider->name }}</p><p class="mt-0.5 text-xs text-[#527189] dark:text-zinc-400">{{ $provider->code }}</p></flux:table.cell>
                                            <flux:table.cell class="tabular-nums">{{ number_format($provider->consumers_count) }}</flux:table.cell>
                                            <flux:table.cell><flux:badge :color="$provider->status === 'active' ? 'green' : 'zinc'" size="sm">{{ ucfirst($provider->status) }}</flux:badge></flux:table.cell>
                                        </flux:table.row>
                                    @empty
                                        <flux:table.row><flux:table.cell colspan="3" class="py-10 text-center text-sm text-[#527189] dark:text-zinc-400">No providers yet. Create one from the provider directory to get started.</flux:table.cell></flux:table.row>
                                    @endforelse
                                </flux:table.rows>
                            </flux:table>
                        </div>
                    </section>

                    <section aria-labelledby="recent-activity-heading">
                        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                            <div><h2 id="recent-activity-heading" class="text-xl font-semibold tracking-tight">Recent activity</h2><p class="mt-1 text-sm text-[#527189] dark:text-zinc-400">The latest recorded changes across AquaFlow.</p></div>
                            <a href="{{ route('admin.overview') }}" wire:navigate class="rounded text-sm font-semibold text-[#0869CF] underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0869CF] dark:text-cyan-300">Open activity log <span aria-hidden="true">→</span></a>
                        </div>
                        <div class="divide-y divide-[#E3EFF4] overflow-hidden rounded-2xl border border-[#D5E8F0] bg-white dark:divide-zinc-700 dark:border-zinc-700 dark:bg-zinc-900">
                            @forelse ($recentAudits as $audit)
                                <div class="flex flex-wrap items-start justify-between gap-x-5 gap-y-1 px-6 py-4 text-sm">
                                    <div class="min-w-0"><p class="font-semibold text-[#062E68] dark:text-slate-100">{{ ucwords(str_replace('.', ' ', $audit->action)) }}</p><p class="mt-1 text-xs text-[#527189] dark:text-zinc-400">{{ $audit->water_provider_id ? 'Provider #'.$audit->water_provider_id : 'Platform' }}</p></div>
                                    <time class="text-xs text-[#527189] dark:text-zinc-400" datetime="{{ $audit->created_at?->toIso8601String() }}">{{ $audit->created_at?->diffForHumans() }}</time>
                                </div>
                            @empty
                                <p class="px-6 py-10 text-center text-sm text-[#527189] dark:text-zinc-400">No recorded activity yet. Changes to platform records will appear here.</p>
                            @endforelse
                        </div>
                    </section>
                </div>

                <aside class="space-y-6">
                    <section aria-labelledby="support-heading" class="rounded-2xl bg-[#063E91] p-6 text-white dark:bg-[#092F68]">
                        <div class="flex items-start justify-between gap-4"><h2 id="support-heading" class="text-base font-semibold">Escalated support</h2><span class="size-2.5 shrink-0 rounded-full bg-[#5BD5F2]" aria-hidden="true"></span></div>
                        <p class="mt-6 text-4xl font-semibold tracking-tight tabular-nums">{{ number_format($escalatedTicketCount) }}</p>
                        <p class="mt-1 text-sm text-[#D8F1F9]">Open escalated tickets</p>
                        <a href="{{ route('admin.support') }}" wire:navigate class="mt-6 inline-flex min-h-10 items-center rounded-lg bg-white px-4 text-sm font-semibold text-[#063E91] transition-colors hover:bg-[#EAF8FC] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">Review tickets <span class="ml-2" aria-hidden="true">→</span></a>
                    </section>

                    <section aria-labelledby="access-heading" class="rounded-2xl border border-[#D5E8F0] bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                        <h2 id="access-heading" class="text-base font-semibold">Manage access</h2>
                        <p class="mt-1 text-sm leading-6 text-[#527189] dark:text-zinc-400">Accounts, roles, and provider workspaces.</p>
                        <div class="mt-5 divide-y divide-[#E3EFF4] border-t border-[#E3EFF4] dark:divide-zinc-700 dark:border-zinc-700">
                            <a href="{{ route('admin.users.index') }}" wire:navigate class="group flex items-center justify-between gap-3 rounded py-3.5 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0869CF]"><span class="group-hover:text-[#0869CF] dark:group-hover:text-cyan-300">Users</span><span class="tabular-nums text-[#527189] dark:text-zinc-400">{{ number_format($userCount) }} <span aria-hidden="true">→</span></span></a>
                            <a href="{{ route('admin.roles.index') }}" wire:navigate class="group flex items-center justify-between gap-3 rounded py-3.5 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0869CF]"><span class="group-hover:text-[#0869CF] dark:group-hover:text-cyan-300">Roles &amp; permissions</span><span class="tabular-nums text-[#527189] dark:text-zinc-400">{{ number_format($roleCount) }} <span aria-hidden="true">→</span></span></a>
                            <a href="{{ route('admin.providers.index') }}" wire:navigate class="group flex items-center justify-between gap-3 rounded py-3.5 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0869CF]"><span class="group-hover:text-[#0869CF] dark:group-hover:text-cyan-300">Providers</span><span class="tabular-nums text-[#527189] dark:text-zinc-400">{{ number_format($providerCount) }} <span aria-hidden="true">→</span></span></a>
                        </div>
                    </section>
                </aside>
            </div>
        </div>
    </main>
</x-layouts::app>
