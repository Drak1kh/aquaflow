<x-layouts::app title="Disconnection &amp; Reconnection">
<div class="provider-page w-full min-w-0 space-y-6">
<x-page-header title="Disconnection & Reconnection" description="Issue notices for overdue accounts and record service restoration after balances are cleared." />

<flux:button :href="route('provider.balances.index')">View outstanding balances</flux:button>
<form method="GET" class="flex flex-col gap-3 sm:flex-row sm:items-end"><flux:input placeholder="Search by consumer name or account number" name="search" label="Consumer or account" :value="request('search')" /><flux:button type="submit">Search</flux:button></form>
@forelse ($connections as $connection)
    <flux:card class="space-y-4">
        <flux:heading level="2">{{ $connection->consumer->name }} · {{ $connection->meter_number }}</flux:heading>
        <flux:text>{{ ucfirst(str_replace('_', ' ', $connection->status)) }} · Balance ₱{{ ($bills->get($connection->consumer_id) ?? collect())->reduce(fn ($sum, $bill) => bcadd($sum, $bill->balance(), 2), '0.00') }}</flux:text>
        @if ($serviceOptions[$connection->id])
        <form method="POST" action="{{ route('provider.service.store', $connection) }}" class="space-y-3">
            @csrf
            <input type="hidden" name="_form" value="service-{{ $connection->id }}">
            <flux:select name="action" label="Service action">
                @foreach ($serviceOptions[$connection->id] as $action)
                    <option value="{{ $action }}">{{ ['notice' => 'Record disconnection notice', 'disconnect' => 'Record disconnection', 'request_reconnection' => 'Request reconnection', 'restore' => 'Complete service restoration'][$action] }}</option>
                @endforeach
            </flux:select>
            <flux:textarea placeholder="Enter the notice, request, or restoration details" name="reason" label="Reason / notice details" rows="2" required>{{ old('_form') === 'service-'.$connection->id ? old('reason') : '' }}</flux:textarea>
            <flux:button type="submit">Record action</flux:button>
        </form>
        @else
            <flux:text>{{ $connection->status === 'active' ? 'No overdue balance: disconnection actions are unavailable.' : 'Clear all outstanding bills on this consumer account before requesting or completing reconnection.' }}</flux:text>
        @endif
    </flux:card>
@empty <flux:text>No connections found.</flux:text> @endforelse
{{ $connections->links() }}
<flux:heading level="2" size="lg">Service history</flux:heading>
@forelse ($events as $event)
    <flux:card>{{ $event->connection->consumer->name }} · {{ ucfirst(str_replace('_', ' ', $event->action)) }} · {{ $event->created_at->format('M d, Y H:i') }}<flux:text>{{ $event->reason }}</flux:text></flux:card>
@empty <flux:text>No service events recorded.</flux:text> @endforelse
{{ $events->withQueryString()->links() }}
</div>
</x-layouts::app>
