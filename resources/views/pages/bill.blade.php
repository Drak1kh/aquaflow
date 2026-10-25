<x-layouts::app :title="__('Bill')">
    <main class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-6 text-[#062E68] dark:text-white">
        <div class="flex items-center justify-between gap-3 print:hidden"><a class="text-[#079EE9] underline" href="{{ url()->previous() }}">Back</a><flux:button onclick="window.print()">Print bill</flux:button></div>
        <flux:card class="space-y-6">
            <header class="flex flex-wrap justify-between gap-4"><div><flux:heading size="xl">{{ $provider->name }}</flux:heading><flux:text>Water service bill</flux:text></div><div class="text-right"><strong>{{ $bill->bill_number }}</strong><p>{{ $bill->period->label }}</p></div></header>
            <div class="grid gap-4 sm:grid-cols-2"><div><flux:text>Consumer</flux:text><strong>{{ $bill->consumer->name }}</strong><p>{{ $bill->consumer->account_number }}</p><p>{{ $bill->connection->service_address }}</p></div><div><flux:text>Due date</flux:text><strong>{{ $bill->due_on }}</strong><p>Connection {{ $bill->connection->connection_number }}</p></div></div>
            <div class="overflow-x-auto"><flux:table><flux:table.columns><flux:table.column>Description</flux:table.column><flux:table.column>Value</flux:table.column></flux:table.columns><flux:table.rows>
                <flux:table.row><flux:table.cell>Previous reading</flux:table.cell><flux:table.cell>{{ $bill->previous_reading }}</flux:table.cell></flux:table.row>
                <flux:table.row><flux:table.cell>Current reading</flux:table.cell><flux:table.cell>{{ $bill->current_reading }}</flux:table.cell></flux:table.row>
                <flux:table.row><flux:table.cell>Consumption</flux:table.cell><flux:table.cell>{{ $bill->consumption }}</flux:table.cell></flux:table.row>
                <flux:table.row><flux:table.cell>Base charge</flux:table.cell><flux:table.cell>{{ $bill->base_charge }}</flux:table.cell></flux:table.row>
                <flux:table.row><flux:table.cell>Consumption charge ({{ $bill->unit_rate }}/unit)</flux:table.cell><flux:table.cell>{{ $bill->consumption_charge }}</flux:table.cell></flux:table.row>
                <flux:table.row><flux:table.cell>Adjustments</flux:table.cell><flux:table.cell>{{ $bill->adjustments->sum('amount') }}</flux:table.cell></flux:table.row>
                <flux:table.row><flux:table.cell>Total bill</flux:table.cell><flux:table.cell>{{ $bill->total_amount }}</flux:table.cell></flux:table.row>
                <flux:table.row><flux:table.cell>Remaining balance</flux:table.cell><flux:table.cell><strong>{{ \App\DecimalAmount::format($bill->balanceCents()) }}</strong></flux:table.cell></flux:table.row>
            </flux:table.rows></flux:table></div>
            <flux:badge>{{ str_replace('_', ' ', $bill->status) }}</flux:badge>
            @if (request()->routeIs('provider.bills.show') && auth()->user()->canInProvider('bills.adjust', $provider->id))
                <form method="POST" action="{{ route('provider.bills.adjust', $bill) }}" class="flex flex-wrap items-end gap-3 print:hidden">@csrf
                    <flux:input name="amount" label="Signed adjustment" type="number" step="0.01" required />
                    <flux:input name="reason" label="Reason" required />
                    <flux:button type="submit">Record adjustment</flux:button>
                </form>
                @if ($bill->status !== 'void')
                    <form method="POST" action="{{ route('provider.bills.void', $bill) }}" class="flex flex-wrap items-end gap-3 print:hidden">@csrf<flux:input name="reason" label="Reason to void" required /><flux:button type="submit">Void bill</flux:button></form>
                @endif
            @endif
        </flux:card>
    </main>
</x-layouts::app>
