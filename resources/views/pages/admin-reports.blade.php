<x-layouts::app :title="__('Platform reports')">
    <main class="flex w-full min-w-0 flex-col gap-8 py-8 text-[#062E68] dark:text-white">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div><flux:heading size="xl">Platform reports</flux:heading><flux:text>Consumers, billing, collections, balances, disputes, and provider performance.</flux:text></div>
            <flux:button type="button" onclick="window.print()" class="print:hidden">Print or save as PDF</flux:button>
        </header>

        <flux:card class="print:hidden">
            <form method="GET" action="{{ route('admin.reports') }}" class="flex flex-wrap items-end gap-3">
                <flux:select name="water_provider_id" label="Provider">
                    <flux:select.option value="">All providers</flux:select.option>
                    @foreach ($providers as $provider)
                        <flux:select.option :value="$provider->id" :selected="($filters['water_provider_id'] ?? null) == $provider->id">{{ $provider->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input name="from" label="From" type="date" value="{{ $filters['from'] ?? '' }}" />
                <flux:input name="to" label="To" type="date" value="{{ $filters['to'] ?? '' }}" />
                <flux:button type="submit" variant="primary">Apply filters</flux:button>
            </form>
        </flux:card>

        <section aria-label="Report totals" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <flux:card><flux:text>Providers</flux:text><flux:heading size="xl">{{ number_format($providerCount) }}</flux:heading></flux:card>
            <flux:card><flux:text>Consumers registered</flux:text><flux:heading size="xl">{{ number_format($consumerCount) }}</flux:heading></flux:card>
            <flux:card><flux:text>Bills generated</flux:text><flux:heading size="xl">{{ number_format($billCount) }}</flux:heading></flux:card>
            <flux:card><flux:text>Verified payments</flux:text><flux:heading size="xl">{{ number_format($paymentCount) }}</flux:heading></flux:card>
            <flux:card><flux:text>Billed amount</flux:text><flux:heading size="xl">{{ \App\DecimalAmount::format($billedCents) }}</flux:heading></flux:card>
            <flux:card><flux:text>Verified collections</flux:text><flux:heading size="xl">{{ \App\DecimalAmount::format($collectedCents) }}</flux:heading></flux:card>
            <flux:card><flux:text>Current outstanding balance</flux:text><flux:heading size="xl">{{ \App\DecimalAmount::format($outstandingCents) }}</flux:heading></flux:card>
            <flux:card><flux:text>Escalated disputes</flux:text><flux:heading size="xl">{{ number_format($disputeCount) }}</flux:heading></flux:card>
        </section>
        <p class="text-sm text-slate-600 dark:text-slate-300">Date filters apply to records created during the selected period. Outstanding balance is a current snapshot for the selected provider.</p>

        <section aria-labelledby="provider-performance-heading" class="min-w-0">
            <flux:card class="space-y-4">
                <flux:heading id="provider-performance-heading" size="lg">Provider performance</flux:heading>
                <div class="overflow-x-auto">
                    <flux:table>
                        <flux:table.columns><flux:table.column>Provider</flux:table.column><flux:table.column>Consumers</flux:table.column><flux:table.column>Bills</flux:table.column><flux:table.column>Billed</flux:table.column><flux:table.column>Collected</flux:table.column><flux:table.column>Current balance</flux:table.column></flux:table.columns>
                        <flux:table.rows>
                            @forelse ($providerRows as $provider)
                                <flux:table.row>
                                    <flux:table.cell>{{ $provider->name }}</flux:table.cell>
                                    <flux:table.cell class="tabular-nums">{{ number_format($provider->report_consumers_count) }}</flux:table.cell>
                                    <flux:table.cell class="tabular-nums">{{ number_format($provider->report_bills_count) }}</flux:table.cell>
                                    <flux:table.cell class="tabular-nums">{{ $provider->report_billed_total ?? '0.00' }}</flux:table.cell>
                                    <flux:table.cell class="tabular-nums">{{ $provider->report_collected_total ?? '0.00' }}</flux:table.cell>
                                    <flux:table.cell class="tabular-nums">{{ \App\DecimalAmount::format($outstandingByProvider[$provider->id] ?? 0) }}</flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row><flux:table.cell colspan="6">No providers match these filters.</flux:table.cell></flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </div>
            </flux:card>
        </section>

        <section aria-labelledby="report-downloads-heading" class="print:hidden">
            <flux:heading id="report-downloads-heading" size="lg">Download reports</flux:heading>
            <div class="mt-4 flex flex-wrap gap-3">
                @foreach (['providers' => 'Providers', 'consumers' => 'Consumers', 'bills' => 'Billing', 'payments' => 'Payments', 'collections' => 'Collections', 'outstanding' => 'Outstanding balances', 'disputes' => 'Disputes'] as $type => $label)
                    <flux:button :href="route('admin.reports.export', [...$filters, 'type' => $type])">{{ $label }} CSV</flux:button>
                @endforeach
            </div>
        </section>
    </main>
</x-layouts::app>
