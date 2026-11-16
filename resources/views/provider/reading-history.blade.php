<section class="space-y-4">
    @php
        $canManageReadings = auth()->user()->can('connections.manage');
        $readingLockReason = fn ($reading) => match (true) {
            ! $canManageReadings => __('You do not have permission to manage readings.'),
            (bool) $reading->bill_id => __('A bill has already been generated for this reading.'),
            $reading->connection->latestReading?->id !== $reading->id => __('A newer reading exists for this meter.'),
            default => null,
        };
    @endphp
    <flux:heading level="2" size="lg">Meter reading history</flux:heading>
    <form method="GET" class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
        <flux:input name="period" type="month" label="Reading month" :value="request('period')" />
        <flux:input placeholder="Search by consumer name or account number" name="search" label="Consumer name or account" :value="request('search')" />
        <flux:button type="submit">Filter connections and history</flux:button>
    </form>
    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
            <thead class="bg-brand-surface text-brand-ink dark:bg-zinc-800 dark:text-white"><tr><th scope="col" class="px-4 py-3">{{ __('Consumer') }}</th><th scope="col" class="px-4 py-3">{{ __('Meter') }}</th><th scope="col" class="px-4 py-3">{{ __('Month') }}</th><th scope="col" class="px-4 py-3 text-right">{{ __('Usage (m³)') }}</th><th scope="col" class="px-4 py-3">{{ __('Status') }}</th><th scope="col" class="px-4 py-3">{{ __('Action') }}</th></tr></thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($history as $reading)
                    @php $lockReason = $readingLockReason($reading); @endphp
                    <tr>
                        <td class="px-4 py-3 font-medium"><a href="{{ route('provider.consumers.index', ['search' => $reading->connection->consumer->account_number]) }}" class="aquaflow-plain-link">{{ $reading->connection->consumer->name }}</a></td>
                        <td class="px-4 py-3">{{ $reading->connection->meter_number }}</td>
                        <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">{{ $reading->period_month->format('M Y') }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ bcsub($reading->current_reading, $reading->previous_reading, 3) }}</td>
                        <td class="px-4 py-3">@if ($reading->bill_id)<a href="{{ route('provider.payments.index', ['bill' => $reading->bill_id]) }}" class="aquaflow-status-link inline-flex rounded-md bg-green-100 px-2 py-1 text-xs font-medium hover:bg-green-200 focus-visible:outline-2 focus-visible:outline-offset-2 dark:bg-green-900/40 dark:hover:bg-green-900/60">{{ __('Bill Generated') }}</a>@else<span class="inline-flex rounded-md bg-zinc-100 px-2 py-1 text-xs font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ __('Pending') }}</span>@endif</td>
                        <td class="px-4 py-3">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button icon="ellipsis-vertical" variant="ghost" :aria-label="__('Reading actions for :meter (:month)', ['meter' => $reading->connection->meter_number, 'month' => $reading->period_month->format('M Y')])" />
                                <flux:menu>
                                    @if ($lockReason)
                                        <flux:menu.item disabled :aria-describedby="'reading-lock-'.$reading->id">{{ __('Edit') }}</flux:menu.item>
                                        <flux:menu.item disabled :aria-describedby="'reading-lock-'.$reading->id">{{ __('Delete') }}</flux:menu.item>
                                        <div id="reading-lock-{{ $reading->id }}" class="max-w-64 px-2 py-1.5 text-xs text-zinc-500 dark:text-zinc-400">{{ $lockReason }}</div>
                                    @else
                                        <flux:modal.trigger :name="'edit-reading-'.$reading->id"><flux:menu.item>{{ __('Edit') }}</flux:menu.item></flux:modal.trigger>
                                        <flux:modal.trigger :name="'delete-reading-'.$reading->id"><flux:menu.item variant="danger">{{ __('Delete') }}</flux:menu.item></flux:modal.trigger>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center">{{ __('No readings match these filters.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @foreach ($history as $reading)
        @if (! $readingLockReason($reading))
            <x-form-modal :name="'edit-reading-'.$reading->id" :title="__('Edit reading')" :description="$reading->connection->meter_number.' · '.$reading->period_month->format('M Y')" :action="route('provider.readings.update', $reading)" method="PUT" :submit-label="__('Save reading')">
                <flux:input name="current_reading" type="number" step="0.001" :min="$reading->previous_reading" :value="old('_form') === 'edit-reading-'.$reading->id ? old('current_reading') : $reading->current_reading" :label="__('Reading')" required />
            </x-form-modal>
            <x-form-modal :name="'delete-reading-'.$reading->id" :title="__('Delete reading')" :description="$reading->connection->meter_number.' · '.$reading->period_month->format('M Y')" :action="route('provider.readings.destroy', $reading)" method="DELETE" :submit-label="__('Delete reading')" variant="danger">
                <flux:text>{{ __('Delete this meter reading? This action cannot be undone.') }}</flux:text>
            </x-form-modal>
        @endif
    @endforeach
    {{ $history->links() }}
</section>
