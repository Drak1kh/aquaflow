<x-layouts::app title="Disconnection &amp; Reconnection">
<div class="provider-page w-full min-w-0 space-y-6">
<x-page-header title="Disconnection & Reconnection" description="Issue notices for overdue accounts and record service restoration after balances are cleared." />

<div class="flex flex-wrap items-center justify-between gap-3">
    <flux:text>A service action applies to one connection, while clearance is checked across the entire consumer account.</flux:text>
    <flux:button :href="route('provider.balances.index')">View outstanding balances</flux:button>
</div>
<div class="grid gap-4 sm:grid-cols-3">
    <flux:card><flux:text>Active connections</flux:text><flux:heading class="tabular-nums" level="2">{{ $serviceStatusCounts['active'] }}</flux:heading></flux:card>
    <flux:card><flux:text>Disconnected</flux:text><flux:heading class="tabular-nums" level="2">{{ $serviceStatusCounts['disconnected'] }}</flux:heading></flux:card>
    <flux:card><flux:text>Pending reconnection</flux:text><flux:heading class="tabular-nums" level="2">{{ $serviceStatusCounts['pending_reconnection'] }}</flux:heading></flux:card>
</div>
<flux:card><flux:heading level="2">Service lifecycle</flux:heading><flux:text>Notice an overdue account, disconnect if needed, clear the entire account balance, request reconnection, then restore service.</flux:text></flux:card>
<form method="GET" class="flex flex-col gap-3 sm:flex-row sm:items-end"><flux:input placeholder="Search by consumer name or account number" name="search" label="Consumer or account" :value="request('search')" /><flux:button type="submit">Search</flux:button></form>
@forelse ($connections as $connection)
    <flux:card class="space-y-4">
        @php
            $balance = ($bills->get($connection->consumer_id) ?? collect())->reduce(fn ($sum, $bill) => bcadd($sum, $bill->balance(), 2), '0.00');
            $action = $serviceOptions[$connection->id][0] ?? null;
            $actionLabel = $action ? ['notice' => 'Record disconnection notice', 'disconnect' => 'Record disconnection', 'request_reconnection' => 'Request reconnection', 'restore' => 'Complete service restoration'][$action] : null;
        @endphp
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><flux:heading level="2">{{ $connection->consumer->name }}</flux:heading><flux:text>Account {{ $connection->consumer->account_number }} · Connection {{ $connection->number }} · Meter {{ $connection->meter_number }}</flux:text></div>
            <flux:badge>{{ ucfirst(str_replace('_', ' ', $connection->status)) }}</flux:badge>
        </div>
        <div class="grid gap-3 text-sm sm:grid-cols-2">
            <div><flux:text>Account-wide balance</flux:text><flux:text class="tabular-nums">₱{{ $balance }}</flux:text></div>
            <div><flux:text>Latest event</flux:text><flux:text>{{ $connection->latestServiceEvent ? ucfirst(str_replace('_', ' ', $connection->latestServiceEvent->action)).' · '.$connection->latestServiceEvent->created_at->format('M d, Y') : 'No service event recorded' }}</flux:text></div>
        </div>
        <a href="{{ route('provider.balances.index', ['search' => $connection->consumer->account_number]) }}" class="text-sm text-brand-primary focus-visible:outline-2 focus-visible:outline-offset-2 dark:text-brand-aqua">View account bills</a>
        @if ($serviceOptions[$connection->id])
        <form method="POST" action="{{ route('provider.service.store', $connection) }}" class="space-y-3">
            @csrf
            <input type="hidden" name="_form" value="service-{{ $connection->id }}">
            <input type="hidden" name="action" value="{{ $action }}">
            <flux:text>Next step: {{ $actionLabel }}</flux:text>
            <flux:textarea placeholder="Enter the notice, request, or restoration details" name="reason" label="Reason / notice details" rows="2" required>{{ old('_form') === 'service-'.$connection->id ? old('reason') : '' }}</flux:textarea>
            <flux:button type="submit">{{ $actionLabel }}</flux:button>
        </form>
        @else
            <flux:text>{{ $connection->status === 'active' ? 'No overdue balance: disconnection actions are unavailable.' : 'Clear all outstanding bills on this consumer account before requesting or completing reconnection.' }}</flux:text>
        @endif
    </flux:card>
@empty <flux:text>No connections found.</flux:text> @endforelse
{{ $connections->links() }}
<flux:heading level="2" size="lg">Service history</flux:heading>
@forelse ($events as $event)
    <flux:card><flux:heading level="2">{{ $event->connection->consumer->name }} · {{ ucfirst(str_replace('_', ' ', $event->action)) }}</flux:heading><flux:text>{{ $event->connection->number }} · {{ $event->created_at->format('M d, Y H:i') }}</flux:text><flux:text>{{ $event->reason }}</flux:text></flux:card>
@empty <flux:text>No service events recorded.</flux:text> @endforelse
{{ $events->withQueryString()->links() }}
</div>
</x-layouts::app>
