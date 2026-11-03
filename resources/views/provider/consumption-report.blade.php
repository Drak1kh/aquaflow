<x-layouts::app title="Water Consumption Reports">
<div class="provider-page w-full min-w-0 space-y-6">
<x-page-header title="Water Consumption Reports" description="Compare usage with the preceding recorded period. Review flags indicate usage at least twice, or at most half, the prior nonzero usage." />

<form method="GET" class="flex flex-wrap items-end gap-3">
    <x-month-input name="month" label="Billing month" :value="request('month')" />
    <flux:input placeholder="Search by consumer name or account number" name="search" label="Consumer or account" :value="request('search')" />
    <flux:button type="submit">Review consumption</flux:button>
</form>
<div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr><th scope="col" class="p-3">Consumer / meter</th><th scope="col" class="p-3">Period</th><th scope="col" class="p-3">Consumption m³</th><th scope="col" class="p-3">Previous period m³</th><th scope="col" class="p-3">Review</th></tr></thead><tbody>
@forelse ($rows as $row)<tr><td class="p-3">{{ $row['reading']->connection->consumer->name }} · {{ $row['reading']->connection->meter_number }}</td><td class="p-3">{{ $row['reading']->period_month->format('M Y') }}</td><td class="p-3">{{ $row['usage'] }}</td><td class="p-3">{{ $row['prior'] ?? '—' }}</td><td class="p-3">{{ $row['flag'] }}</td></tr>
@empty <tr><td class="p-3" colspan="5">No readings found.</td></tr> @endforelse
</tbody></table></div>
{{ $readings->links() }}
</div>
</x-layouts::app>
