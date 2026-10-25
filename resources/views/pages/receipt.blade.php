<x-layouts::app :title="__('Payment receipt')">
    <main class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-6 text-[#062E68] dark:text-white">
        <div class="flex justify-end print:hidden"><flux:button onclick="window.print()">Print receipt</flux:button></div>
        <flux:card class="space-y-5">
            <header><flux:heading size="xl">{{ $provider->name }}</flux:heading><flux:text>Payment receipt</flux:text></header>
            <div class="grid gap-3 sm:grid-cols-2"><p>Reference<br><strong>{{ $payment->reference_number }}</strong></p><p>Verified<br><strong>{{ $payment->verified_at }}</strong></p><p>Consumer<br><strong>{{ $payment->consumer->name }}</strong></p><p>Method<br><strong>{{ str_replace('_', ' ', $payment->method) }}</strong></p></div>
            <div class="overflow-x-auto"><flux:table><flux:table.columns><flux:table.column>Bill</flux:table.column><flux:table.column>Applied amount</flux:table.column></flux:table.columns><flux:table.rows>
                @foreach ($payment->allocations as $allocation)<flux:table.row><flux:table.cell>{{ $allocation->bill->bill_number }}</flux:table.cell><flux:table.cell>{{ $allocation->amount }}</flux:table.cell></flux:table.row>@endforeach
            </flux:table.rows></flux:table></div>
            <div class="border-t border-[#BDECF8] pt-4 text-lg font-semibold dark:border-zinc-700">Total received: {{ $payment->amount }}</div>
        </flux:card>
    </main>
</x-layouts::app>
