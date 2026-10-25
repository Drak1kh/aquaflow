<x-layouts::app :title="__('Bill preview')">
    <main class="flex w-full min-w-0 flex-col gap-6 py-8 text-[#062E68] dark:text-white">
        <header><flux:heading size="xl">Bill preview</flux:heading><flux:text>{{ $provider->name }} · {{ $period->label }} · {{ $reading->connection->consumer->name }}</flux:text></header>
        <flux:card class="space-y-4">
            <flux:text>Connection {{ $reading->connection->connection_number }} · Reading {{ $reading->reading_date }}</flux:text>
            <div class="overflow-x-auto"><flux:table><flux:table.columns><flux:table.column>Item</flux:table.column><flux:table.column>Value</flux:table.column></flux:table.columns><flux:table.rows>
                @foreach (['previous_reading' => 'Previous reading', 'current_reading' => 'Current reading', 'consumption' => 'Consumption', 'base_charge' => 'Base charge', 'unit_rate' => 'Price per unit', 'consumption_charge' => 'Consumption charge', 'total_amount' => 'Total'] as $key => $label)
                    <flux:table.row><flux:table.cell>{{ $label }}</flux:table.cell><flux:table.cell>{{ $snapshot[$key] }}</flux:table.cell></flux:table.row>
                @endforeach
            </flux:table.rows></flux:table></div>
            <form method="POST" action="{{ route('provider.bills.generate') }}">@csrf<input type="hidden" name="billing_period_id" value="{{ $period->id }}"><input type="hidden" name="meter_reading_id" value="{{ $reading->id }}"><flux:button type="submit" variant="primary">Generate this bill</flux:button></form>
        </flux:card>
    </main>
</x-layouts::app>
