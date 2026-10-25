<x-layouts::app :title="__('Provider workspace')">
    <main class="flex w-full min-w-0 flex-col gap-8 py-8 text-[#062E68] dark:text-white">
        <header><flux:heading size="xl">{{ $provider->name }}</flux:heading><flux:text>Consumers, connections, and meter readings</flux:text></header>
        @include('partials.aquaflow-alerts')

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <flux:card><flux:text>Consumers</flux:text><flux:heading size="xl">{{ $dashboard['consumer_count'] }}</flux:heading></flux:card>
            <flux:card><flux:text>Active connections</flux:text><flux:heading size="xl">{{ $dashboard['active_connections'] }}</flux:heading></flux:card>
            <flux:card><flux:text>Disconnected connections</flux:text><flux:heading size="xl">{{ $dashboard['disconnected_connections'] }}</flux:heading></flux:card>
            <flux:card><flux:text>Unpaid bills</flux:text><flux:heading size="xl">{{ $dashboard['unpaid_bills'] }}</flux:heading></flux:card>
            <flux:card><flux:text>Outstanding</flux:text><flux:heading size="xl">{{ \App\DecimalAmount::format($dashboard['outstanding_cents']) }}</flux:heading></flux:card>
            <flux:card><flux:text>Verified collections</flux:text><flux:heading size="xl">{{ \App\DecimalAmount::format($dashboard['collected_cents']) }}</flux:heading></flux:card>
        </div>

        @if (auth()->user()->canInProvider('provider.manage', $provider->id))
            <flux:card class="space-y-4"><flux:heading>Provider profile</flux:heading>
                <form method="POST" action="{{ route('provider.profile.update') }}" class="grid gap-4 md:grid-cols-4">@csrf @method('PUT')
                    <flux:input name="email" label="Office email" type="email" value="{{ $provider->email }}" />
                    <flux:input name="contact_number" label="Phone" value="{{ $provider->contact_number }}" />
                    <flux:input name="office_address" label="Office address" value="{{ $provider->office_address }}" />
                    <div class="flex items-end"><flux:button type="submit">Save profile</flux:button></div>
                </form>
            </flux:card>
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            @if (auth()->user()->canInProvider('consumers.manage', $provider->id))
                <flux:card class="space-y-4">
                    <flux:heading>Register consumer</flux:heading>
                    <form method="POST" action="{{ route('provider.consumers.store') }}" class="space-y-4">
                        @csrf
                        <flux:input name="account_number" label="Account number" required />
                        <flux:input name="name" label="Full name" required />
                        <flux:input name="email" label="Email" type="email" />
                        <flux:input name="contact_number" label="Contact number" />
                        <flux:textarea name="service_address" label="Service address" required />
                        <flux:button type="submit" variant="primary">Register consumer</flux:button>
                    </form>
                </flux:card>
            @endif
            @if (auth()->user()->canInProvider('connections.manage', $provider->id))
                <flux:card class="space-y-4">
                    <flux:heading>Assign connection and meter</flux:heading>
                    <form method="POST" action="{{ route('provider.connections.store') }}" class="space-y-4">
                        @csrf
                        <flux:select name="consumer_id" label="Consumer" required>
                            @foreach ($consumerChoices as $consumer)
                                <flux:select.option value="{{ $consumer->id }}">{{ $consumer->account_number }} · {{ $consumer->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:input name="connection_number" label="Connection number" required />
                        <flux:input name="meter_number" label="Meter number" required />
                        <flux:textarea name="service_address" label="Service address" required />
                        <flux:button type="submit" variant="primary">Assign connection</flux:button>
                    </form>
                </flux:card>
            @endif
        </div>

        <flux:card class="space-y-4">
            <flux:heading>Consumers</flux:heading>
            <form method="GET" action="{{ route('provider.index') }}" class="flex flex-wrap gap-2"><flux:input name="q" label="Search name or account" value="{{ $search }}" /><div class="flex items-end"><flux:button type="submit">Search</flux:button></div></form>
            <div class="overflow-x-auto"><flux:table>
                <flux:table.columns><flux:table.column>Account</flux:table.column><flux:table.column>Name</flux:table.column><flux:table.column>Connections</flux:table.column><flux:table.column>Portal</flux:table.column></flux:table.columns>
                <flux:table.rows>
                    @foreach ($consumers as $consumer)
                        <flux:table.row>
                            <flux:table.cell>{{ $consumer->account_number }}</flux:table.cell>
                            <flux:table.cell>
                                {{ $consumer->name }}
                                @if (auth()->user()->canInProvider('consumers.manage', $provider->id))
                                    <details class="mt-2"><summary class="cursor-pointer text-[#079EE9]">Edit</summary>
                                        <form method="POST" action="{{ route('provider.consumers.update', $consumer) }}" class="mt-2 grid gap-2">@csrf @method('PUT')
                                            <input name="name" value="{{ $consumer->name }}" aria-label="Name" required class="rounded border p-1 dark:bg-zinc-800">
                                            <input name="email" value="{{ $consumer->email }}" aria-label="Email" type="email" @readonly($consumer->user_id) class="rounded border p-1 dark:bg-zinc-800">
                                            <input name="contact_number" value="{{ $consumer->contact_number }}" aria-label="Contact number" class="rounded border p-1 dark:bg-zinc-800">
                                            <input name="service_address" value="{{ $consumer->service_address }}" aria-label="Service address" required class="rounded border p-1 dark:bg-zinc-800">
                                            <select name="status" aria-label="Status" class="rounded border p-1 dark:bg-zinc-800"><option value="active" @selected($consumer->status === 'active')>Active</option><option value="inactive" @selected($consumer->status === 'inactive')>Inactive</option></select>
                                            <flux:button type="submit" size="sm">Save</flux:button>
                                        </form>
                                    </details>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $consumer->connections_count }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($consumer->user_id)
                                    Linked
                                @elseif ($consumer->email && auth()->user()->canInProvider('consumers.manage', $provider->id))
                                    <form method="POST" action="{{ route('provider.consumers.invite', $consumer) }}">@csrf<flux:button type="submit" size="sm">Invite</flux:button></form>
                                @else
                                    No email
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table></div>
            {{ $consumers->links() }}
        </flux:card>

        <flux:card class="space-y-4">
            <flux:heading>Connections and readings</flux:heading>
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($connections as $connection)
                    <div class="rounded-xl border border-[#BDECF8] p-4 dark:border-zinc-700">
                        <div class="flex items-center justify-between gap-2"><strong>{{ $connection->connection_number }}</strong><flux:badge>{{ str_replace('_', ' ', $connection->status) }}</flux:badge></div>
                        <p class="text-sm">{{ $connection->consumer->name }} · Meter {{ $connection->meter->meter_number }}</p>
                        @if (auth()->user()->canInProvider('readings.manage', $provider->id))
                            <form method="POST" action="{{ route('provider.readings.store', $connection) }}" class="mt-4 flex flex-wrap items-end gap-3">
                                @csrf
                                <flux:input name="reading_date" label="Reading date" type="date" required />
                                <flux:input name="current_reading" label="Current reading" type="number" step="0.001" min="0" required />
                                <flux:button type="submit" variant="primary">Record</flux:button>
                            </form>
                        @endif
                        <p class="mt-3 text-xs font-medium">Recent readings</p>
                        <ul class="text-sm">@foreach ($connection->readings->sortByDesc('reading_date')->take(3) as $reading)<li>{{ $reading->reading_date }} · {{ $reading->previous_reading }} → {{ $reading->current_reading }}</li>@endforeach</ul>
                        @if (auth()->user()->canInProvider('connections.manage', $provider->id))
                            <details class="mt-3"><summary class="cursor-pointer text-sm text-[#079EE9]">Replace meter</summary>
                                <form method="POST" action="{{ route('provider.meters.replace', $connection) }}" class="mt-2 flex flex-wrap items-end gap-2">@csrf<flux:input name="meter_number" label="New meter number" required /><flux:input name="type" label="Meter type" value="standard" required /><flux:button type="submit">Replace</flux:button></form>
                            </details>
                        @endif
                    </div>
                @endforeach
            </div>
        </flux:card>
    </main>
</x-layouts::app>
