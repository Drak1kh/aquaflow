<x-layouts::app :title="__('Escalated disputes')">
    <div class="provider-page w-full min-w-0 space-y-6">
        <x-page-header :title="__('Escalated disputes')" :description="__('Review customer concerns, coordinate with providers, and keep internal staff notes separate from customer replies.')" />
        <form method="GET" action="{{ route('admin.tickets.index') }}" class="flex flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-4 sm:flex-row sm:flex-wrap sm:items-end dark:border-zinc-700 dark:bg-zinc-900">
            <div class="min-w-0 flex-1 sm:min-w-48">
                <flux:input name="search" label="Search tickets" icon="magnifying-glass" placeholder="Ticket #, subject, customer, account, or provider…" :value="request('search')" maxlength="255" />
            </div>
            <div class="sm:w-48">
                <flux:select name="status" label="Status">
                    <option value="">All statuses</option>
                    @foreach (['open', 'under_review', 'referred_to_provider', 'waiting_for_consumer', 'resolved', 'closed'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </flux:select>
            </div>
            <div class="sm:w-40">
                <flux:select name="type" label="Type">
                    <option value="">All types</option>
                    @foreach (['billing', 'payment', 'account', 'service', 'provider', 'other'] as $type)
                        <option value="{{ $type }}" @selected(request('type') === $type)>{{ ucfirst($type) }}</option>
                    @endforeach
                </flux:select>
            </div>
            <flux:button type="submit">Filter tickets</flux:button>
            @if (request()->filled('search') || request()->filled('status') || request()->filled('type'))
                <flux:button :href="route('admin.tickets.index')" variant="ghost">Clear</flux:button>
            @endif
        </form>
        <x-ticket-workspace :tickets="$tickets" :selected-ticket="$selectedTicket" :admin="true" :admins="$admins" />
    </div>
</x-layouts::app>