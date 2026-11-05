<x-layouts::app title="Dashboard">
<div class="provider-page w-full min-w-0 space-y-6">
    <x-page-header title="Dashboard" :description="$provider->name">
        <x-slot:actions>
            @can('permission', 'connections.manage')
                <flux:button :href="route('provider.readings.index')" variant="primary" icon="clipboard-document-check">{{ __('Record readings') }}</flux:button>
            @endcan
            @can('permission', 'billing.manage')
                <flux:button :href="route('provider.billing.index')" icon="document-currency-dollar">{{ __('Issue bills') }}</flux:button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <section aria-labelledby="operational-attention" class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <flux:heading id="operational-attention" level="2" size="lg">{{ __('Operational attention') }}</flux:heading>
            <flux:text>{{ __('Items that need follow-up today') }}</flux:text>
        </div>
        <div class="grid gap-4 md:grid-cols-3">
            @can('permission', 'payments.view')
                <a href="{{ route('provider.payments.index') }}" data-kpi-card class="rounded-xl border border-zinc-200 bg-white p-5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0869CF] dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:text class="text-amber-800 dark:text-amber-200">{{ __('Pending payments') }}</flux:text>
                    <flux:heading level="3" size="xl" class="mt-2 tabular-nums text-amber-950 dark:text-amber-100">{{ $pendingPayments }}</flux:heading>
                    <flux:text class="mt-1 text-sm text-amber-800 dark:text-amber-200">{{ __('Awaiting verification') }}</flux:text>
                </a>
            @endcan
            @can('permission', 'billing.view')
                <a href="{{ route('provider.balances.index') }}" data-kpi-card class="rounded-xl border border-zinc-200 bg-white p-5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0869CF] dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:text class="text-rose-800 dark:text-rose-200">{{ __('Overdue bills') }}</flux:text>
                    <flux:heading level="3" size="xl" class="mt-2 tabular-nums text-rose-950 dark:text-rose-100">{{ $overdueBills }}</flux:heading>
                    <flux:text class="mt-1 text-sm text-rose-800 dark:text-rose-200">{{ __('Past their due date with a balance') }}</flux:text>
                </a>
            @endcan
            @can('permission', 'tickets.view')
                <a href="{{ route('provider.tickets.index') }}" data-kpi-card class="rounded-xl border border-zinc-200 bg-white p-5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0869CF] dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:text class="text-sky-800 dark:text-sky-200">{{ __('Open customer requests') }}</flux:text>
                    <flux:heading level="3" size="xl" class="mt-2 tabular-nums text-sky-950 dark:text-sky-100">{{ $openTickets }}</flux:heading>
                    <flux:text class="mt-1 text-sm text-sky-800 dark:text-sky-200">{{ __('Support tickets still in progress') }}</flux:text>
                </a>
            @endcan
        </div>
    </section>

    @include('provider.metrics')

    <flux:card>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <flux:heading level="2" size="lg">{{ __('Billing rules') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('Current provider charging and collection settings') }}</flux:text>
                </div>
                <flux:button :href="route('provider.profile.edit')" variant="ghost" size="sm">{{ __('Manage rules') }}</flux:button>
            </div>
            <dl class="mt-5 grid gap-4 sm:grid-cols-3">
                <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Base charge') }}</dt><dd class="mt-1 font-semibold tabular-nums">{{ $currentRate ? 'PHP '.number_format((float) $currentRate->base_charge, 2) : __('Not set') }}</dd></div>
                <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Water rate') }}</dt><dd class="mt-1 font-semibold tabular-nums">{{ $currentRate ? 'PHP '.number_format((float) $currentRate->unit_rate, 2).' / m3' : __('Not set') }}</dd></div>
                <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Payment window') }}</dt><dd class="mt-1 font-semibold tabular-nums">{{ $provider->due_days ?? 14 }} {{ __('days') }} @if (($provider->grace_days ?? 0) > 0) / {{ $provider->grace_days }} {{ __('grace') }} @endif</dd></div>
            </dl>
    </flux:card>

    <div class="grid gap-6 xl:grid-cols-2">
        @can('billing.view')
            <x-dashboard-chart :title="__('Billing and collections')" :description="__('Bills by billing month; verified payments by payment date.')" :data="$financialTrend" :series="auth()->user()->can('payments.view') ? ['billed' => __('Billed'), 'collected' => __('Collected')] : ['billed' => __('Billed')]" />
        @endcan
        <x-dashboard-chart :title="__('Water consumption')" :description="__('Total metered usage across your provider’s connections by reading month.')" :data="$consumptionTrend" :series="['consumption' => __('Consumption')]" unit="m3" />
    </div>

    <section class="space-y-4">
        <flux:heading level="2" size="lg">{{ __('Latest activity') }}</flux:heading>
        <div class="grid gap-6 xl:grid-cols-2">
            <flux:card>
                <flux:heading level="3" size="lg">{{ __('Bills') }}</flux:heading>
            <div class="mt-4 space-y-3">
                @forelse ($bills->take(5) as $bill)
                    <div class="flex items-center justify-between gap-3"><flux:text><a href="{{ route('bills.print', $bill) }}" data-document-preview>{{ $bill->number }}</a> / {{ $bill->consumer->name }}</flux:text><flux:text class="shrink-0 tabular-nums">PHP {{ number_format((float) $bill->total, 2) }}</flux:text></div>
                @empty <flux:text>{{ __('No bills generated yet.') }}</flux:text> @endforelse
            </div>
            </flux:card>
            <flux:card>
                <flux:heading level="3" size="lg">{{ __('Payments') }}</flux:heading>
            <div class="mt-4 space-y-3">
                @forelse ($payments->take(5) as $payment)
                    <div class="flex items-center justify-between gap-3"><flux:text>{{ $payment->reference }} / {{ ucfirst($payment->status) }}</flux:text><flux:text class="shrink-0 tabular-nums">PHP {{ number_format((float) $payment->amount, 2) }}</flux:text></div>
                @empty <flux:text>{{ __('No payments recorded yet.') }}</flux:text> @endforelse
            </div>
            </flux:card>
        </div>
    </section>
</div>
</x-layouts::app>
