<x-layouts::app title="Support Tickets">
    <div class="provider-page w-full min-w-0 space-y-6">
        <x-page-header title="Customer Support & Inquiries" description="Review billing, payment, and service concerns. Respond to customers or escalate disputes for administrative review." />

        <form method="GET" action="{{ route('provider.tickets.index') }}" class="flex flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-4 sm:flex-row sm:items-end dark:border-zinc-700 dark:bg-zinc-900">
            <div class="min-w-0 flex-1">
                <flux:input name="search" label="Search tickets" icon="magnifying-glass" placeholder="Ticket #, subject, customer, or account…" :value="request('search')" maxlength="255" />
            </div>
            <div class="sm:w-52">
                <flux:select name="status" label="Status">
                    <option value="">All statuses</option>
                    @foreach (['open', 'under_review', 'referred_to_provider', 'waiting_for_consumer', 'resolved', 'closed'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </flux:select>
            </div>
            <flux:button type="submit">Filter tickets</flux:button>
            @if (request()->filled('search') || request()->filled('status'))
                <flux:button :href="route('provider.tickets.index')" variant="ghost">Clear</flux:button>
            @endif
        </form>

        <x-ticket-workspace :tickets="$tickets" :selected-ticket="$selectedTicket" />
    </div>
</x-layouts::app>
