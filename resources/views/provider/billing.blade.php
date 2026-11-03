<x-layouts::app title="Billing Management">
<div class="provider-page w-full min-w-0 space-y-6">
<x-page-header title="Billing Management" />

<form method="GET" class="flex flex-col gap-3 sm:flex-row sm:items-end"><flux:input placeholder="Search by consumer name or account number" name="search" label="Search consumer name or account" :value="request('search')" /><flux:button type="submit">Search</flux:button></form>
        @can('permission', 'billing.view')
            <section class="space-y-4">
                <flux:heading level="2" size="lg">Billing periods</flux:heading>
                <form method="POST" action="{{ route('provider.periods.store') }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <x-month-input name="period_month" label="Billing month" required />
                    <flux:input placeholder="Select the due date" name="due_at" type="date" label="Due date" required />
                    <flux:button type="submit">Create billing period</flux:button>
                </form>
                @foreach ($periods as $period)
                    <flux:text>{{ $period->period_month->format('M Y') }} · Due {{ $period->due_at->format('M d, Y') }}</flux:text>
                @endforeach
                {{ $periods->links() }}
            </section>
            <section class="space-y-3">
                <flux:heading level="2">Configured water rates</flux:heading>
                @forelse ($rates as $rate)
                    <flux:text>Effective {{ $rate->effective_from->format('M d, Y') }} · Base ₱{{ $rate->base_charge }} · ₱{{ $rate->unit_rate }} per m³</flux:text>
                @empty <flux:text>Set a water rate before generating bills.</flux:text> @endforelse
            </section>
            <section id="billing" class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <flux:heading level="2" size="lg">{{ __('Billing') }}</flux:heading>
                    @can('permission', 'billing.manage')
                        <div class="flex flex-wrap gap-2">
                            <flux:modal.trigger name="create-water-rate"><flux:button>{{ __('Set water rate') }}</flux:button></flux:modal.trigger>
                            <flux:modal.trigger name="create-bill"><flux:button variant="primary">{{ __('Generate bill') }}</flux:button></flux:modal.trigger>
                            <flux:modal.trigger name="create-batch-bills"><flux:button>{{ __('Generate monthly batch') }}</flux:button></flux:modal.trigger>
                        </div>
                    @endcan
                </div>
                <flux:text>Connection choices show up to 50 matches. Use the page search to find a consumer.</flux:text>
                @can('permission', 'billing.manage')
                    <form method="GET" action="{{ route('provider.billing.index') }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                        <flux:select name="connection" :label="__('Connection to preview')"><option value="">{{ __('Choose connection') }}</option>
                            @foreach ($connections as $connection)
                                <option value="{{ $connection->id }}" @selected(request('connection') == $connection->id)>{{ $connection->number }}</option>
                            @endforeach
                        </flux:select>
                        <x-month-input name="month" :value="request('month')" :label="__('Month')" required />
                        <flux:button type="submit">{{ __('Preview bill') }}</flux:button>
                        @if ($preview)
                            <div class="w-full rounded-lg bg-brand-surface p-3 text-sm text-brand-ink dark:bg-zinc-800 dark:text-white">{{ __('Consumption') }}: {{ $preview['consumption'] }} m³ · {{ __('Total') }}: ₱{{ $preview['total'] }}</div>
                        @endif
                    </form>
                @endcan
                <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                    <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
                        <thead class="bg-brand-surface text-brand-ink dark:bg-zinc-800 dark:text-white"><tr><th scope="col" class="px-4 py-3">{{ __('Bill') }}</th><th scope="col" class="px-4 py-3">{{ __('Consumer') }}</th><th scope="col" class="px-4 py-3">{{ __('Month') }}</th><th scope="col" class="px-4 py-3 text-right">{{ __('Total') }}</th><th scope="col" class="px-4 py-3 text-right">{{ __('Balance') }}</th></tr></thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse ($bills as $bill)
                                <tr><td class="px-4 py-3"><a href="{{ route('bills.print', $bill) }}">{{ $bill->number }} · {{ $bill->paymentStatus() }}</a></td><td class="px-4 py-3">{{ $bill->consumer->name }}</td><td class="px-4 py-3">{{ $bill->period_month->format('M Y') }}</td><td class="px-4 py-3 text-right tabular-nums">₱{{ number_format((float) $bill->total, 2) }}</td><td class="px-4 py-3 text-right tabular-nums">₱{{ number_format((float) $bill->balance(), 2) }}</td></tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-8 text-center">{{ __('No bills generated yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $bills->links() }}
                @can('permission', 'billing.manage')
                    <x-form-modal name="create-water-rate" :title="__('Set water rate')" :action="route('provider.rates.store')" :submit-label="__('Save rate')">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:input placeholder="e.g. 50.00" name="base_charge" type="number" step="0.01" min="0" :value="old('_form') === 'create-water-rate' ? old('base_charge') : null" :label="__('Base charge (PHP)')" required />
                            <flux:input placeholder="e.g. 10.00" name="unit_rate" type="number" step="0.01" min="0" :value="old('_form') === 'create-water-rate' ? old('unit_rate') : null" :label="__('Per m³ (PHP)')" required />
                            <flux:input placeholder="Select the effective date" name="effective_from" type="date" :value="old('_form') === 'create-water-rate' ? old('effective_from') : null" :label="__('Effective from')" required />
                        </div>
                    </x-form-modal>
                    <x-form-modal name="create-bill" :title="__('Generate monthly bill')" :action="route('provider.bills.store')" :submit-label="__('Generate bill')">
                        <div class="space-y-4">
                            <flux:select name="connection_id" :label="__('Connection')" required>
                                <option value="">{{ __('Choose connection') }}</option>
                                @foreach ($connections as $connection)
                                    <option value="{{ $connection->id }}" @selected((old('_form') === 'create-bill' ? old('connection_id') : request('connection')) == $connection->id)>{{ $connection->number }}</option>
                                @endforeach
                            </flux:select>
                            <x-month-input name="period_month" :value="old('_form') === 'create-bill' ? old('period_month') : request('month')" :label="__('Month')" required />
                        </div>
                    </x-form-modal>
                    <x-form-modal name="create-batch-bills" :title="__('Generate monthly batch')" :description="__('Active connections with a reading for the month will be billed. Existing bills are skipped.')" :action="route('provider.bills.batch')" :submit-label="__('Generate monthly batch')">
                        <x-month-input name="period_month" :value="old('_form') === 'create-batch-bills' ? old('period_month') : null" :label="__('Month')" required />
                    </x-form-modal>
                @endcan
            </section>
        @endcan


</div>
</x-layouts::app>
