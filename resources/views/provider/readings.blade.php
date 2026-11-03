<x-layouts::app title="Meter Readings">
<div class="provider-page w-full min-w-0 space-y-6">
<x-page-header title="Meter Readings" />

<form method="GET" class="flex flex-col gap-3 sm:flex-row sm:items-end"><flux:input placeholder="Search by consumer name or account number" name="search" label="Search consumer name or account" :value="request('search')" /><flux:button type="submit">Search</flux:button></form>
        @can('permission', 'connections.view')
            <section id="connections" class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <flux:heading level="2" size="lg">{{ __('Connections and readings') }}</flux:heading>
                    @can('permission', 'connections.manage')
                        <div class="flex flex-wrap gap-2">
                            <flux:modal.trigger name="create-connection"><flux:button icon="plus">{{ __('Add connection') }}</flux:button></flux:modal.trigger>
                            <flux:modal.trigger name="create-reading"><flux:button variant="primary">{{ __('Record reading') }}</flux:button></flux:modal.trigger>
                        </div>
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
                    <flux:text>Consumer and connection choices show up to 50 matches. Use the page search to narrow the choices.</flux:text>
                    <x-form-modal name="create-connection" :title="__('Add connection')" :action="route('provider.connections.store')" :submit-label="__('Add connection')">
                        <div class="space-y-4">
                            <flux:select name="consumer_id" :label="__('Consumer')" required>
                                <option value="">{{ __('Choose consumer') }}</option>
                                @foreach ($consumers as $consumer)
                                    <option value="{{ $consumer->id }}" @selected(old('_form') === 'create-connection' && old('consumer_id') == $consumer->id)>{{ $consumer->name }}</option>
                                @endforeach
                            </flux:select>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <flux:input placeholder="e.g. CON-000123" name="number" :value="old('_form') === 'create-connection' ? old('number') : null" :label="__('Connection number')" required />
                                <flux:input placeholder="e.g. MTR-000123" name="meter_number" :value="old('_form') === 'create-connection' ? old('meter_number') : null" :label="__('Meter number')" required />
                            </div>
                            <flux:input placeholder="Select the installation date" name="installed_at" type="date" :value="old('_form') === 'create-connection' ? old('installed_at') : null" :label="__('Installation date')" />
                        </div>
                    </x-form-modal>
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
