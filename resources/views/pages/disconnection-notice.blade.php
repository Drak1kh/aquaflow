<x-layouts::app :title="__('Service notice')">
    <main class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-6 text-[#062E68] dark:text-white">
        <div class="flex justify-end print:hidden"><flux:button onclick="window.print()">Print notice</flux:button></div>
        <flux:card class="space-y-5">
            <header><flux:heading size="xl">Disconnection notice</flux:heading><flux:text>{{ $provider->name }}</flux:text></header>
            <p><strong>Issued:</strong> {{ $action->created_at->toDateString() }}</p>
            <p><strong>Consumer:</strong> {{ $connection->consumer->name }} · {{ $connection->consumer->account_number }}</p>
            <p><strong>Connection:</strong> {{ $connection->connection_number }} · {{ $connection->service_address }}</p>
            <p><strong>Overdue balance at issuance:</strong> {{ $action->balance_at_action }}</p>
            <p>Your account met the provider's overdue service policy at the time this notice was issued. Contact {{ $provider->name }} to resolve the balance or discuss the required steps before service interruption.</p>
            @if ($action->notes)<p><strong>Provider notes:</strong> {{ $action->notes }}</p>@endif
        </flux:card>
    </main>
</x-layouts::app>
