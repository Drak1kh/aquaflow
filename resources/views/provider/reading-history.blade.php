<section class="space-y-4">
    <flux:heading level="2" size="lg">Meter reading history</flux:heading>
    <form method="GET" class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
        <flux:input name="period" type="month" label="Reading month" :value="request('period')" />
        <flux:input placeholder="Search by consumer name or account number" name="search" label="Consumer name or account" :value="request('search')" />
        <flux:button type="submit">Filter connections and history</flux:button>
    </form>
    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
            <thead class="bg-brand-surface text-brand-ink dark:bg-zinc-800 dark:text-white"><tr><th scope="col" class="px-4 py-3">{{ __('Consumer') }}</th><th scope="col" class="px-4 py-3">{{ __('Meter') }}</th><th scope="col" class="px-4 py-3">{{ __('Month') }}</th><th scope="col" class="px-4 py-3 text-right">{{ __('Usage (m³)') }}</th><th scope="col" class="px-4 py-3">{{ __('Status') }}</th></tr></thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($history as $reading)
                    <tr>
                        <td class="px-4 py-3 font-medium"><a href="{{ route('provider.consumers.index', ['search' => $reading->connection->consumer->account_number]) }}" class="aquaflow-plain-link">{{ $reading->connection->consumer->name }}</a></td>
                        <td class="px-4 py-3">{{ $reading->connection->meter_number }}</td>
                        <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">{{ $reading->period_month->format('M Y') }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ bcsub($reading->current_reading, $reading->previous_reading, 3) }}</td>
                        <td class="px-4 py-3">@if ($reading->bill_id)<a href="{{ route('provider.payments.index', ['bill' => $reading->bill_id]) }}" class="aquaflow-status-link inline-flex rounded-md bg-green-100 px-2 py-1 text-xs font-medium hover:bg-green-200 focus-visible:outline-2 focus-visible:outline-offset-2 dark:bg-green-900/40 dark:hover:bg-green-900/60">{{ __('Bill Generated') }}</a>@else<span class="inline-flex rounded-md bg-zinc-100 px-2 py-1 text-xs font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ __('Pending') }}</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center">{{ __('No readings match these filters.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $history->links() }}
</section>
