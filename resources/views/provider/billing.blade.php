<x-layouts::app title="Billing Management">
<div class="provider-page w-full min-w-0 space-y-6">
<x-page-header title="Billing Management" />

        @can('permission', 'billing.view')
            @php($currentRate = $rates->first())
            <section class="grid gap-4 sm:grid-cols-2">
                <flux:card><flux:text>{{ __('Current water rate') }}</flux:text>@if ($currentRate)<flux:heading level="2" size="lg" class="mt-2 tabular-nums">₱{{ number_format((float) $currentRate->unit_rate, 2) }} / m³</flux:heading><flux:text class="mt-1 text-sm">{{ __('Base charge: ₱:amount', ['amount' => number_format((float) $currentRate->base_charge, 2)]) }}</flux:text>@else<flux:heading level="2" size="lg" class="mt-2">{{ __('Not set') }}</flux:heading><flux:text class="mt-1 text-sm">{{ __('Set a rate before billing.') }}</flux:text>@endif</flux:card>
                <flux:card>
                    <figure aria-label="{{ __('Unit-rate history') }}" class="@container">
                        <figcaption class="flex flex-wrap items-baseline justify-between gap-x-2">
                            <flux:text>{{ __('Six-month rate history') }}</flux:text>
                            <flux:text class="text-xs">{{ __('Unit rate (₱ / m³)') }}</flux:text>
                        </figcaption>
                        @if ($rateHistory->isEmpty())
                            <flux:text class="mt-3 text-sm">{{ __('No rate records yet.') }}</flux:text>
                        @else
                            @php($maximumRate = max(1, (float) $rateHistory->max('unit_rate')))
                            <div class="mt-5 grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-1">
                                <div role="group" aria-label="{{ __('Unit rate scale') }}" class="relative h-16 text-right text-xs tabular-nums text-zinc-600 dark:text-zinc-400">
                                    <div aria-hidden="true" class="invisible">₱{{ number_format($maximumRate, 2) }}</div>
                                    @foreach ([4, 3, 2, 1, 0] as $tick)
                                        <span class="absolute right-0 -translate-y-1/2 whitespace-nowrap" style="top: {{ (4 - $tick) * 25 }}%">₱{{ number_format($maximumRate * $tick / 4, 2) }}</span>
                                    @endforeach
                                </div>
                                <div class="relative h-16 border-b border-l border-zinc-300 dark:border-zinc-600">
                                    <div aria-hidden="true" class="absolute inset-0">
                                        @foreach ([0, 25, 50, 75] as $position)
                                            <div class="absolute inset-x-0 border-t border-zinc-200 dark:border-zinc-700" style="top: {{ $position }}%"></div>
                                        @endforeach
                                    </div>
                                    <div role="list" aria-label="{{ __('Unit rates by effective month') }}" class="relative flex h-full items-end justify-around gap-2 px-2">
                                        @foreach ($rateHistory as $rate)
                                            <div role="listitem" aria-label="{{ $rate->effective_from->format('F Y') }} — ₱{{ number_format((float) $rate->unit_rate, 2) }} / m³" class="relative flex h-full min-w-0 flex-1 items-end">
                                                <div aria-hidden="true" class="relative w-full rounded-t-sm bg-brand-primary dark:bg-brand-aqua" style="height: {{ ((float) $rate->unit_rate / $maximumRate) * 100 }}%">
                                                    <span class="absolute bottom-full left-1/2 mb-1 hidden -translate-x-1/2 whitespace-nowrap text-xs font-medium tabular-nums text-zinc-800 @sm:block dark:text-zinc-100">₱{{ number_format((float) $rate->unit_rate, 2) }}</span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                <div aria-hidden="true" class="col-start-2 hidden justify-around gap-2 px-2 text-center text-xs text-zinc-600 @sm:flex dark:text-zinc-400">
                                    @foreach ($rateHistory as $rate)
                                        <span class="min-w-0 flex-1">{{ $rate->effective_from->format('M') }}<span class="block">{{ $rate->effective_from->format('Y') }}</span></span>
                                    @endforeach
                                </div>
                            </div>
                            <dl aria-label="{{ __('Monthly unit rates') }}" class="mt-2 grid grid-cols-3 gap-x-3 gap-y-1 text-xs @sm:hidden">
                                @foreach ($rateHistory as $rate)
                                    <div class="min-w-0">
                                        <dt class="text-zinc-600 dark:text-zinc-400">{{ $rate->effective_from->format('M Y') }}</dt>
                                        <dd class="break-words font-medium tabular-nums text-zinc-800 dark:text-zinc-100">₱{{ number_format((float) $rate->unit_rate, 2) }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        @endif
                    </figure>
                </flux:card>
            </section>
            <section id="billing" class="space-y-4">
                <div class="flex flex-wrap items-end gap-2">
                    <form method="GET" class="flex flex-wrap items-end gap-2"><flux:input placeholder="Search by consumer name or account number" name="search" label="Search consumer name or account" :value="request('search')" /><flux:button type="submit">Search</flux:button></form>
                    <div class="flex flex-wrap items-end gap-2 sm:ms-auto">
                    @can('permission', 'billing.manage')
                        <div class="flex flex-wrap items-end gap-2">
                            <flux:modal.trigger name="create-water-rate"><flux:button>{{ __('Set water rate') }}</flux:button></flux:modal.trigger>
                            <flux:modal.trigger name="create-bill"><flux:button variant="primary">{{ __('Generate bill') }}</flux:button></flux:modal.trigger>
                        </div>
                    @endcan
                    </div>
                </div>
                <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                    <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
                        <thead class="bg-brand-surface text-brand-ink dark:bg-zinc-800 dark:text-white"><tr><th scope="col" class="px-4 py-3">{{ __('Bill receipt') }}</th><th scope="col" class="px-4 py-3">{{ __('Consumer') }}</th>@foreach (['period_month' => __('Month'), 'total' => __('Total'), 'balance' => __('Balance')] as $column => $label)@php($isCurrentSort = $sort === $column)@php($nextDirection = $isCurrentSort && $direction === 'desc' ? 'asc' : 'desc')<th scope="col" aria-sort="{{ $isCurrentSort ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}" class="px-4 py-3 {{ $column === 'period_month' ? '' : 'text-right' }}"><div class="flex items-center gap-1 {{ $column === 'period_month' ? '' : 'justify-end' }}"><span>{{ $label }}</span><a href="{{ route('provider.billing.index', array_merge(request()->except('page'), ['sort' => $column, 'direction' => $nextDirection])) }}" class="inline-flex size-6 items-center justify-center rounded hover:bg-zinc-200/60 dark:hover:bg-white/10 {{ $isCurrentSort ? 'text-brand-primary dark:text-brand-aqua' : '' }}" aria-label="Sort {{ $label }} {{ $nextDirection === 'asc' ? 'ascending' : 'descending' }}"><flux:icon.chevrons-up-down class="size-4! [stroke-width:3]" /></a></div></th>@endforeach<th scope="col" class="px-4 py-3">{{ __('Status') }}</th></tr></thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @if ($bills->isEmpty())
                                <tr><td colspan="6" class="px-4 py-8 text-center">{{ __('No bills generated yet.') }}</td></tr>
                            @else
                                @foreach ($sort === 'period_month' ? $bills->groupBy(fn ($bill) => $bill->period_month->format('Y-m')) : [null => $bills] as $month => $monthBills)
                                    @if ($month !== null)<tr class="bg-brand-surface dark:bg-zinc-800"><th colspan="6" scope="colgroup" class="px-4 py-2 text-left text-sm font-semibold text-brand-ink dark:text-white">{{ $monthBills->first()->period_month->format('F Y') }}</th></tr>@endif
                                    @foreach ($monthBills as $bill)
                                        @php($status = $bill->paymentStatus())@php($statusColor = match ($status) { 'Paid' => 'green', 'Partially Paid', 'Unpaid' => 'yellow', 'Overdue' => 'red', default => 'zinc' })<tr><td class="px-4 py-3"><a href="{{ route('bills.print', $bill) }}" data-document-preview>{{ $bill->number }}</a></td><td class="px-4 py-3">{{ $bill->consumer->name }}</td><td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">{{ $bill->period_month->format('M Y') }}</td><td class="px-4 py-3 text-right tabular-nums">₱{{ number_format((float) $bill->total, 2) }}</td><td class="px-4 py-3 text-right tabular-nums">₱{{ number_format((float) $bill->balance(), 2) }}</td><td class="px-4 py-3"><flux:badge :color="$statusColor">{{ $status }}</flux:badge></td></tr>
                                    @endforeach
                                @endforeach
                            @endif
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
                            <flux:select name="connection_id" :label="__('Connection')">
                                <option value="">{{ __('Choose connection') }}</option>
                                @foreach ($connections as $connection)
                                    <option value="{{ $connection->id }}" @selected((old('_form') === 'create-bill' ? old('connection_id') : request('connection')) == $connection->id)>{{ $connection->number }}</option>
                                @endforeach
                            </flux:select>
                            <div x-data="{ dueDate: @js(old('due_at', now()->endOfMonth()->toDateString())), monthStart: @js(old('_form') === 'create-bill' ? old('period_month') : request('month', now()->format('Y-m'))) }" class="space-y-4">
                                <flux:input x-model="monthStart" x-on:input="if (!monthStart) return; const [year, month] = monthStart.split('-').map(Number); const date = new Date(year, month, 0); dueDate = [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')).join('-')" name="period_month" type="month" :label="__('Month')" required />
                                <flux:field>
                                    <flux:label for="due_at">{{ __('Due date') }} <span class="text-red-600" aria-hidden="true">*</span><span class="sr-only">{{ __('Required') }}</span></flux:label>
                                    <flux:input id="due_at" name="due_at" type="date" x-model="dueDate" x-bind:min="monthStart ? monthStart + '-01' : null" required />
                                    <flux:error name="due_at" />
                                </flux:field>
                            </div>
                        </div>
                        <x-slot:footer>
                            <flux:text class="text-sm">{{ __('No connection selected? Bills will be generated for all eligible unbilled connections.') }}</flux:text>
                        </x-slot:footer>
                    </x-form-modal>
                @endcan
            </section>
        @endcan


</div>
</x-layouts::app>
