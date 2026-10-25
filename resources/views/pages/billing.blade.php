<x-layouts::app :title="__('Billing')">
    <main class="mx-auto flex w-full max-w-7xl flex-col gap-8 p-6 text-[#062E68] dark:text-white">
        <header><flux:heading size="xl">Billing · {{ $provider->name }}</flux:heading><flux:text>Rates, periods, generated bills, and adjustments</flux:text></header>
        @include('partials.aquaflow-alerts')

        <div class="grid gap-6 lg:grid-cols-3">
            @if (auth()->user()->canInProvider('rates.manage', $provider->id))
                <flux:card class="space-y-4"><flux:heading>New rate</flux:heading>
                    <form method="POST" action="{{ route('provider.rates.store') }}" class="space-y-4">@csrf
                        <flux:input name="effective_from" label="Effective from" type="date" required />
                        <flux:input name="base_charge" label="Base charge" type="number" step="0.01" min="0" required />
                        <flux:input name="unit_rate" label="Price per unit" type="number" step="0.0001" min="0" required />
                        <flux:button type="submit" variant="primary">Save rate</flux:button>
                    </form>
                </flux:card>
            @endif
            @if (auth()->user()->canInProvider('periods.manage', $provider->id))
                <flux:card class="space-y-4"><flux:heading>New billing period</flux:heading>
                    <form method="POST" action="{{ route('provider.periods.store') }}" class="space-y-4">@csrf
                        <flux:input name="label" label="Label" required />
                        <flux:input name="starts_on" label="Starts" type="date" required />
                        <flux:input name="ends_on" label="Ends" type="date" required />
                        <flux:input name="due_on" label="Due date" type="date" required />
                        <flux:button type="submit" variant="primary">Save period</flux:button>
                    </form>
                </flux:card>
            @endif
            @if (auth()->user()->canInProvider('bills.generate', $provider->id))
                <flux:card class="space-y-4"><flux:heading>Generate bill</flux:heading>
                    <form method="POST" action="{{ route('provider.bills.generate') }}" class="space-y-4">@csrf
                        <flux:select name="billing_period_id" label="Period" required>
                            @foreach ($periods as $period)
                                <flux:select.option value="{{ $period->id }}">{{ $period->label }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:select name="meter_reading_id" label="Reading" required>
                            @foreach ($readings as $reading)
                                <flux:select.option value="{{ $reading->id }}">{{ $reading->connection->connection_number }} · {{ $reading->reading_date }} · {{ $reading->current_reading }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:button type="submit" variant="primary">Generate bill</flux:button>
                    </form>
                    <form method="GET" action="{{ route('provider.bills.preview') }}" class="space-y-3 border-t border-[#BDECF8] pt-3 dark:border-zinc-700">
                        <flux:select name="billing_period_id" label="Preview period" required>@foreach ($periods as $period)<flux:select.option value="{{ $period->id }}">{{ $period->label }}</flux:select.option>@endforeach</flux:select>
                        <flux:select name="meter_reading_id" label="Preview reading" required>@foreach ($readings as $reading)<flux:select.option value="{{ $reading->id }}">{{ $reading->connection->connection_number }} · {{ $reading->reading_date }}</flux:select.option>@endforeach</flux:select>
                        <flux:button type="submit">Preview</flux:button>
                    </form>
                </flux:card>
            @endif
        </div>

        @if (auth()->user()->canInProvider('bills.generate', $provider->id))
            <flux:card class="space-y-4"><flux:heading>Batch generation</flux:heading><flux:text>Generate one bill from the latest reading in each connection for the selected period. Existing bills are skipped.</flux:text>
                <form method="POST" action="{{ route('provider.bills.batch') }}" class="flex flex-wrap items-end gap-3">@csrf
                    <flux:select name="billing_period_id" label="Period" required>@foreach ($periods as $period)<flux:select.option value="{{ $period->id }}">{{ $period->label }}</flux:select.option>@endforeach</flux:select>
                    <flux:button type="submit" variant="primary">Generate batch</flux:button>
                </form>
            </flux:card>
        @endif

        <flux:card class="space-y-4">
            <flux:heading>Current rates</flux:heading>
            <div class="flex flex-wrap gap-3">
                @forelse ($rates as $rate)
                    <flux:badge>{{ $rate->effective_from }} · Base {{ $rate->base_charge }} + {{ $rate->unit_rate }}/unit</flux:badge>
                @empty
                    <flux:text>No rate configured yet.</flux:text>
                @endforelse
            </div>
        </flux:card>

        <flux:card class="space-y-4"><flux:heading>Bills</flux:heading>
            <div class="overflow-x-auto"><flux:table>
                <flux:table.columns><flux:table.column>Bill</flux:table.column><flux:table.column>Consumer</flux:table.column><flux:table.column>Period</flux:table.column><flux:table.column>Total</flux:table.column><flux:table.column>Balance</flux:table.column><flux:table.column>Status</flux:table.column></flux:table.columns>
                <flux:table.rows>
                    @foreach ($bills as $bill)
                        <flux:table.row>
                            <flux:table.cell><a class="text-[#079EE9] underline" href="{{ route('provider.bills.show', $bill) }}">{{ $bill->bill_number }}</a></flux:table.cell>
                            <flux:table.cell>{{ $bill->consumer->name }}</flux:table.cell>
                            <flux:table.cell>{{ $bill->period->label }}</flux:table.cell>
                            <flux:table.cell>{{ $bill->total_amount }}</flux:table.cell>
                            <flux:table.cell>{{ \App\DecimalAmount::format($bill->balanceCents()) }}</flux:table.cell>
                            <flux:table.cell><flux:badge>{{ str_replace('_', ' ', $bill->status) }}</flux:badge></flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table></div>
            {{ $bills->links() }}
        </flux:card>
    </main>
</x-layouts::app>
