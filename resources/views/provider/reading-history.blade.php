<section class="space-y-4">
    <flux:heading level="2" size="lg">Meter reading history</flux:heading>
    <form method="GET" class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
        <x-month-input name="period" label="Reading month" :value="request('period')" />
        <flux:input placeholder="Search by consumer name or account number" name="search" label="Consumer name or account" :value="request('search')" />
        <flux:button type="submit">Filter connections and history</flux:button>
    </form>
    @forelse ($history as $reading)
        <article class="space-y-3 border-b border-zinc-200 py-4 dark:border-zinc-700">
            <flux:heading level="3">{{ $reading->connection->consumer->name }} · {{ $reading->connection->meter_number }}</flux:heading>
            <flux:text>{{ $reading->period_month->format('M Y') }} · {{ $reading->previous_reading }} → {{ $reading->current_reading }} · {{ bcsub($reading->current_reading, $reading->previous_reading, 3) }} m³</flux:text>
            @if (!$reading->bill_count && !$reading->later_count)
                <form method="POST" action="{{ route('provider.readings.update', $reading) }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    @csrf @method('PUT')
                    <input type="hidden" name="_form" value="correct-{{ $reading->id }}">
                    <flux:input placeholder="e.g. 115.500" name="current_reading" type="number" step="0.001" min="0" :value="old('_form') === 'correct-'.$reading->id ? old('current_reading') : $reading->current_reading" label="Correct current reading" required />
                    <flux:button type="submit">Save correction</flux:button>
                </form>
            @else
                <flux:text>Locked: {{ $reading->bill_count ? 'a bill has been generated from this reading.' : 'a later reading uses this value.' }}</flux:text>
            @endif
        </article>
    @empty
        <flux:text>No readings match these filters.</flux:text>
    @endforelse
    {{ $history->links() }}
</section>
