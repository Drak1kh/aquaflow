<x-layouts::app title="Billing Reports">
<div class="provider-page w-full min-w-0 space-y-6">
<x-page-header title="Billing Reports" description="Summaries by bill generation date. Charges include approved adjustments and penalties." />

<form method="GET" class="flex flex-wrap items-end gap-3">
    <flux:select name="group" label="Summary period">@foreach (['daily', 'monthly', 'yearly'] as $value)<option value="{{ $value }}" @selected($group === $value)>{{ ucfirst($value) }}</option>@endforeach</flux:select>
    <flux:input placeholder="Start date" name="from" type="date" label="From" :value="request('from')" />
    <flux:input placeholder="End date" name="to" type="date" label="To" :value="request('to')" />
    <flux:button type="submit">Generate report</flux:button>
</form>
<div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr><th scope="col" class="p-3">Period</th><th scope="col" class="p-3">Bills generated</th><th scope="col" class="p-3">Total charges</th></tr></thead><tbody>
@forelse ($rows as $row)<tr><td class="p-3">{{ $row->report_period }}</td><td class="p-3">{{ $row->bill_count }}</td><td class="p-3">₱{{ bcadd((string) $row->charges, '0', 2) }}</td></tr>
@empty <tr><td class="p-3" colspan="3">No bills for this period.</td></tr> @endforelse
</tbody></table></div>
{{ $rows->links() }}
</div>
</x-layouts::app>
