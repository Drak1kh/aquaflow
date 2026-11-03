<div class="grid gap-4 sm:grid-cols-3">
    <flux:card><flux:heading level="2">Registered consumers</flux:heading><flux:text>{{ $consumerCount }}</flux:text></flux:card>
    <flux:card><flux:heading level="2">Connections</flux:heading><flux:text>{{ $activeConnections }} active · {{ $disconnectedConnections }} disconnected</flux:text></flux:card>
    <flux:card><flux:heading level="2">Bills this month</flux:heading><flux:text>{{ $monthlyBills }}</flux:text></flux:card>
    <flux:card><flux:heading level="2">Collected payments</flux:heading><flux:text>₱{{ number_format((float) $collected, 2) }}</flux:text></flux:card>
    <flux:card><flux:heading level="2">Outstanding balance</flux:heading><flux:text>₱{{ $outstanding }}</flux:text></flux:card>
</div>
