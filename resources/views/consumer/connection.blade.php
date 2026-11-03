<x-layouts::app title="My Connection">
<div class="client-page w-full min-w-0 space-y-6">
<x-page-header title="My Connection" description="Your provider maintains these official connection and meter records. Use Help & Support to request a correction." />
@forelse ($connections as $connection)
<article class="space-y-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
    <flux:heading level="2" size="lg">Connection {{ $connection->number }}</flux:heading>
    <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    @foreach (['Meter number' => $connection->meter_number, 'Connection status' => ucfirst(str_replace('_', ' ', $connection->status)), 'Service address' => $consumer->address, 'Installation date' => $connection->installed_at?->format('M d, Y') ?? 'Not recorded', 'Previous reading' => $connection->latestReading?->previous_reading ?? 'Not recorded', 'Current reading' => $connection->latestReading?->current_reading ?? 'Not recorded', 'Latest billing period' => $connection->latestReading?->period_month->format('M Y') ?? 'Not recorded'] as $label => $value)
        <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ $label }}</dt><dd class="mt-1 font-medium">{{ $value }}</dd></div>
    @endforeach
    @if ($connection->latestReading)<div><dt class="text-sm text-zinc-500 dark:text-zinc-400">Latest consumption</dt><dd class="mt-1 font-medium">{{ bcsub($connection->latestReading->current_reading, $connection->latestReading->previous_reading, 3) }} m³</dd></div>@endif
    </dl>
    <flux:link :href="route('portal.section', ['section' => 'connection', 'connection' => $connection->id])">View this meter’s history</flux:link>
</article>
@empty <flux:text>Your provider has not assigned a water connection yet.</flux:text> @endforelse
{{ $connections->links() }}
<section class="space-y-4">
<flux:heading level="2" size="lg">Meter reading history</flux:heading>
<form method="GET" class="flex flex-col gap-3 sm:flex-row sm:items-end">
    @if(request('connection'))<input type="hidden" name="connection" value="{{ request('connection') }}">@endif
    <x-month-input name="month" label="Billing month" :value="request('month')" />
    <flux:button type="submit">Filter history</flux:button><flux:button :href="route('portal.section', 'connection')">Show all meters</flux:button>
</form>
<div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr><th scope="col" class="p-3">Meter</th><th scope="col" class="p-3">Period</th><th scope="col" class="p-3">Previous</th><th scope="col" class="p-3">Current</th><th scope="col" class="p-3">Consumption m³</th><th scope="col" class="p-3">Recorded</th></tr></thead><tbody>
@forelse($readings as $reading)<tr><td class="p-3">{{ $reading->connection->meter_number }}</td><td class="p-3">{{ $reading->period_month->format('M Y') }}</td><td class="p-3">{{ $reading->previous_reading }}</td><td class="p-3">{{ $reading->current_reading }}</td><td class="p-3">{{ bcsub($reading->current_reading, $reading->previous_reading, 3) }}</td><td class="p-3">{{ $reading->created_at->format('M d, Y') }}</td></tr>
@empty <tr><td colspan="6" class="p-4">No readings match this period.</td></tr> @endforelse
</tbody></table></div>{{ $readings->links() }}
</section>
<section class="space-y-3"><flux:heading level="2" size="lg">Service and request history</flux:heading>
@forelse($events as $event)<flux:card>{{ ucfirst(str_replace('_', ' ', $event->action)) }} · {{ $event->connection->meter_number }} · {{ $event->created_at->format('M d, Y') }}<flux:text>{{ $event->reason }}</flux:text></flux:card>
@empty <flux:text>No service changes recorded.</flux:text> @endforelse
{{ $events->links() }}
</section>
</div>
</x-layouts::app>
