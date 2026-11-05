<x-layouts::app title="Meter Readings">
<div class="provider-page w-full min-w-0 space-y-6">
<x-page-header title="Meter Readings" />

        @can('permission', 'connections.view')
            <section data-meter-reading-summary class="grid w-full gap-4 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_2.5fr]">
                <flux:card class="h-40 flex flex-col justify-center gap-2 text-center"><flux:heading level="2" size="sm">{{ __('Recorded readings') }}</flux:heading><flux:text class="text-3xl font-semibold">{{ $recordedReadingCount }}</flux:text></flux:card>
                <flux:card class="h-40 flex flex-col justify-center gap-2 text-center"><flux:heading level="2" size="sm">{{ __('Active connections') }}</flux:heading><flux:text class="text-3xl font-semibold">{{ $activeConnectionCount }}</flux:text></flux:card>
                <flux:card class="h-40 flex flex-col justify-center gap-2 text-center"><flux:heading level="2" size="sm">{{ __('Not Active connections') }}</flux:heading><flux:text class="text-3xl font-semibold">{{ $inactiveConnectionCount }}</flux:text></flux:card>
                <x-dashboard-chart compact class="h-40 min-w-0" :title="__('Water consumption')" :description="__('Total metered usage across your provider’s connections by reading month.')" :data="$consumptionTrend" :series="['consumption' => __('Consumption')]" unit="m3" />
            </section>

            <section id="connections" class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <flux:heading level="2" size="lg">{{ __('Connections') }}</flux:heading>
                    @can('permission', 'connections.manage')
                        <flux:modal.trigger name="create-reading"><flux:button variant="primary">{{ __('Record reading') }}</flux:button></flux:modal.trigger>
                    @endcan
                </div>
                <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                    <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
                        <thead class="bg-brand-surface text-brand-ink dark:bg-zinc-800 dark:text-white"><tr><th scope="col" class="px-4 py-3">{{ __('Connection') }}</th><th scope="col" class="px-4 py-3">{{ __('Consumer') }}</th><th scope="col" class="px-4 py-3">{{ __('Meter') }}</th><th scope="col" class="px-4 py-3">{{ __('Status') }}</th></tr></thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse ($connections as $connection)
                                <tr><td class="px-4 py-3">{{ $connection->number }}</td><td class="px-4 py-3">{{ $connection->consumer->name }}</td><td class="px-4 py-3">{{ $connection->meter_number }}</td><td class="px-4 py-3">{{ ucfirst($connection->status) }}</td></tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-8 text-center">{{ __('No connections yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $connections->links() }}
                @can('permission', 'connections.manage')
                    <x-form-modal name="create-reading" :title="__('Record meter reading')" :action="route('provider.readings.store')" :submit-label="__('Save reading')">
                        <div class="space-y-4">
                            <flux:select name="connection_id" :label="__('Connection')" required>
                                <option value="">{{ __('Choose connection') }}</option>
                                @foreach ($connectionChoices as $connection)
                                    <option value="{{ $connection->id }}" @selected(old('_form') === 'create-reading' && old('connection_id') == $connection->id)>{{ $connection->number }} · {{ $connection->consumer->name }}</option>
                                @endforeach
                            </flux:select>
                            <flux:input placeholder="e.g. 100.000" :value="old('_form') === 'create-reading' ? old('previous_reading') : null" name="previous_reading" type="number" step="0.001" min="0" label="Initial previous reading (first reading only)" />
                            <flux:text>Later readings use the last recorded reading automatically.</flux:text>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <x-month-input name="period_month" :value="old('_form') === 'create-reading' ? old('period_month') : null" :label="__('Billing month')" required />
                                <flux:input placeholder="e.g. 115.500" name="current_reading" type="number" step="0.001" min="0" :value="old('_form') === 'create-reading' ? old('current_reading') : null" :label="__('Current meter reading')" required />
                            </div>
                        </div>
                    </x-form-modal>
                @endcan
            </section>
        @endcan

@include('provider.reading-history')
</div>
</x-layouts::app>
