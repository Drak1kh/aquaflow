<x-layouts::app :title="__('Service status')">
    <main class="mx-auto flex w-full max-w-7xl flex-col gap-8 p-6 text-[#062E68] dark:text-white">
        <header><flux:heading size="xl">Service status · {{ $provider->name }}</flux:heading><flux:text>Notices, disconnection, and restoration</flux:text></header>
        @include('partials.aquaflow-alerts')

        @if (auth()->user()->canInProvider('provider.manage', $provider->id))
            <flux:card class="space-y-4"><flux:heading>Provider rules</flux:heading>
                <form method="POST" action="{{ route('provider.service.rules') }}" class="grid gap-4 md:grid-cols-4">@csrf @method('PUT')
                    <flux:input name="grace_days" label="Late fee grace days" type="number" min="0" value="{{ $provider->grace_days }}" required />
                    <flux:input name="disconnection_days" label="Disconnection after days" type="number" min="1" value="{{ $provider->disconnection_days }}" required />
                    <flux:input name="late_fee" label="Late fee" type="number" step="0.01" min="0" value="{{ $provider->late_fee }}" required />
                    <flux:input name="reconnection_fee" label="Reconnection fee" type="number" step="0.01" min="0" value="{{ $provider->reconnection_fee }}" required />
                    <div class="flex items-end"><flux:button type="submit" variant="primary">Save rules</flux:button></div>
                </form>
            </flux:card>
        @endif

        <flux:card class="space-y-4"><flux:heading>Connections</flux:heading>
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($connections as $connection)
                    <div class="space-y-3 rounded-xl border border-[#BDECF8] p-4 dark:border-zinc-700">
                        <div class="flex justify-between gap-2"><strong>{{ $connection->connection_number }} · {{ $connection->consumer->name }}</strong><flux:badge>{{ str_replace('_', ' ', $connection->status) }}</flux:badge></div>
                        <form method="POST" action="{{ route('provider.service.act', $connection) }}" class="flex flex-wrap items-end gap-3">@csrf
                            <flux:select name="action" label="Action" required><flux:select.option value="notice">Issue notice</flux:select.option><flux:select.option value="disconnect">Confirm disconnection</flux:select.option><flux:select.option value="reconnection_request">Request reconnection</flux:select.option><flux:select.option value="approve">Approve reconnection</flux:select.option><flux:select.option value="restore">Confirm restoration</flux:select.option></flux:select>
                            <flux:input name="notes" label="Notes" />
                            <flux:button type="submit" variant="primary">Record</flux:button>
                        </form>
                    </div>
                @endforeach
            </div>
        </flux:card>
        <flux:card class="space-y-4"><flux:heading>Recent service history</flux:heading>
            <div class="overflow-x-auto"><flux:table><flux:table.columns><flux:table.column>When</flux:table.column><flux:table.column>Connection ID</flux:table.column><flux:table.column>Action</flux:table.column><flux:table.column>Notes</flux:table.column></flux:table.columns><flux:table.rows>
                @foreach ($actions as $action)
                    <flux:table.row><flux:table.cell>{{ $action->created_at }}</flux:table.cell><flux:table.cell>{{ $action->water_connection_id }}</flux:table.cell><flux:table.cell>@if ($action->action === 'notice')<a class="text-[#079EE9] underline" href="{{ route('provider.service.notice', $action) }}">Print notice</a>@else{{ str_replace('_', ' ', $action->action) }}@endif</flux:table.cell><flux:table.cell>{{ $action->notes }}</flux:table.cell></flux:table.row>
                @endforeach
            </flux:table.rows></flux:table></div>
        </flux:card>
    </main>
</x-layouts::app>
