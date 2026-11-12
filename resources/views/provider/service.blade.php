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
<flux:card><flux:heading level="2">Service lifecycle</flux:heading><flux:text>Disconnect accounts with two outstanding billing months. Reconnect when the entire account balance is cleared.</flux:text></flux:card>
<form method="GET" class="flex flex-col gap-3 sm:flex-row sm:items-end"><flux:input placeholder="Search by consumer name or account number" name="search" label="Consumer or account" :value="request('search')" /><flux:button type="submit">Search</flux:button></form>

<div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
    <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
        <thead class="bg-brand-surface text-brand-ink dark:bg-zinc-800 dark:text-white"><tr><th scope="col" class="px-4 py-3">Consumer</th><th scope="col" class="px-4 py-3">Account</th><th scope="col" class="px-4 py-3">Connection</th><th scope="col" class="px-4 py-3">Meter</th><th scope="col" class="px-4 py-3">Status</th><th scope="col" class="px-4 py-3 text-right">Account-wide balance</th><th scope="col" class="px-4 py-3">Latest event</th><th scope="col" class="px-4 py-3">Next step</th><th scope="col" class="px-4 py-3">Action</th></tr></thead>
        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
            @forelse ($connections as $connection)
                @php
                    $balance = ($bills->get($connection->consumer_id) ?? collect())->reduce(fn ($sum, $bill) => bcadd($sum, $bill->balance(), 2), '0.00');
                    $action = $serviceOptions[$connection->id][0] ?? null;
                    $actionLabel = $action ? ['disconnect' => 'Disconnection Needed', 'reconnect' => 'Reconnection Needed'][$action] : null;
                @endphp
                <tr>
                    <td class="px-4 py-3 font-medium">{{ $connection->consumer->name }}</td>
                    <td class="px-4 py-3">{{ $connection->consumer->account_number }}</td>
                    <td class="px-4 py-3">{{ $connection->number }}</td>
                    <td class="px-4 py-3">{{ $connection->meter_number }}</td>
                    <td class="px-4 py-3"><flux:badge :color="$connection->status === 'active' ? 'green' : 'red'">{{ ucfirst(str_replace('_', ' ', $connection->status)) }}</flux:badge></td>
                    <td class="px-4 py-3 text-right tabular-nums">&#8369;{{ $balance }}</td>
                    <td class="px-4 py-3">{{ $connection->latestServiceEvent ? ucfirst(str_replace('_', ' ', $connection->latestServiceEvent->action)).' · '.$connection->latestServiceEvent->created_at->format('M d, Y') : 'No service event recorded' }}</td>
                    @if ($action)
                        <td class="px-4 py-3">{{ $actionLabel }}</td>
                    @else
                        <td class="px-4 py-3">{{ $connection->status === 'active' ? 'Two outstanding billing months are required before disconnection.' : 'Clear all outstanding bills on this consumer account before reconnecting.' }}</td>
                    @endif
                    <td class="px-4 py-3"><flux:dropdown position="bottom" align="end"><flux:button class="size-11" icon="ellipsis-vertical" variant="ghost" aria-label="{{ __('Service actions') }}" /><flux:menu><flux:menu.item :href="route('provider.balances.index', ['search' => $connection->consumer->account_number])">{{ __('View bills') }}</flux:menu.item>@if ($action === 'disconnect')<flux:modal.trigger :name="'disconnect-'.$connection->id"><flux:menu.item>{{ __('Disconnect') }}</flux:menu.item></flux:modal.trigger>@elseif ($action === 'reconnect')<form method="POST" action="{{ route('provider.service.store', $connection) }}">@csrf<input type="hidden" name="action" value="reconnect"><flux:menu.item as="button" type="submit">{{ __('Reconnect') }}</flux:menu.item></form>@endif</flux:menu></flux:dropdown></td>
                </tr>
            @empty
                <tr><td colspan="9" class="px-4 py-8 text-center">No connections found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@foreach ($connections as $connection)
    @if (($serviceOptions[$connection->id][0] ?? null) === 'disconnect')
        <x-form-modal :name="'disconnect-'.$connection->id" :title="__('Disconnect service')" :description="$connection->consumer->name.' · '.$connection->number" :action="route('provider.service.store', $connection)" :submit-label="__('Disconnect')" variant="danger">
            <input type="hidden" name="action" value="disconnect">
            <flux:textarea name="reason" :label="__('Disconnection notice reason')" required>{{ old('_form') === 'disconnect-'.$connection->id ? old('reason') : '' }}</flux:textarea>
        </x-form-modal>
    @endif
@endforeach
{{ $connections->links() }}

<flux:heading level="2" size="lg">Service history</flux:heading>
<div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
    <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
        <thead class="bg-brand-surface text-brand-ink dark:bg-zinc-800 dark:text-white"><tr><th scope="col" class="px-4 py-3">Consumer</th><th scope="col" class="px-4 py-3">Connection</th><th scope="col" class="px-4 py-3">Action</th><th scope="col" class="px-4 py-3">Recorded</th><th scope="col" class="px-4 py-3">Reason</th></tr></thead>
        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
            @forelse ($events as $event)
                <tr><td class="px-4 py-3 font-medium">{{ $event->connection->consumer->name }}</td><td class="px-4 py-3">{{ $event->connection->number }}</td><td class="px-4 py-3">{{ ucfirst(str_replace('_', ' ', $event->action)) }}</td><td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">{{ $event->created_at->format('M d, Y H:i') }}</td><td class="px-4 py-3">{{ $event->reason }}</td></tr>
            @empty
                <tr><td colspan="5" class="px-4 py-8 text-center">No service events recorded.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $events->withQueryString()->links() }}

</div>
</x-layouts::app>
