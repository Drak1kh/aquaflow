<x-layouts::app :title="$section === 'settings' ? 'Profile Settings' : ($section === 'balances' ? 'Outstanding Balance Management' : 'Reports')">
    <div class="provider-page w-full min-w-0 space-y-6">
        <x-page-header :title="$section === 'settings' ? 'Profile Settings' : ($section === 'balances' ? 'Outstanding Balance Management' : 'Reports')" />


        @if ($section === 'settings')
            <form method="POST" action="{{ route('provider.profile') }}" class="space-y-4">
                @csrf @method('PUT')
                @foreach (['name' => ['Provider name', 'text'], 'address' => ['Office address', 'text'], 'phone' => ['Contact number', 'tel'], 'email' => ['Contact email', 'email'], 'due_days' => ['Due days after month end', 'number'], 'grace_days' => ['Grace period (days)', 'number'], 'late_fee' => ['Late fee (PHP)', 'number']] as $field => [$label, $type])
                    <flux:input :placeholder="match ($field) { 'name' => 'Enter the provider name', 'address' => 'Enter the office address', 'phone' => 'e.g. 09123456789', 'email' => 'e.g. office@example.com', 'due_days' => 'e.g. 14', 'grace_days' => 'e.g. 7', 'late_fee' => 'e.g. 20.00' }" :name="$field" :value="old($field, $provider->$field)" :label="$label" :type="$type" :min="$type === 'number' ? 0 : null" :step="$field === 'late_fee' ? '0.01' : ($type === 'number' ? '1' : null)" :required="in_array($field, ['name', 'due_days'])" />
                @endforeach
                <flux:text>Due days are counted after the end of the billing month. Water rates are configured under Billing.</flux:text>
                <flux:button type="submit" variant="primary">Save settings</flux:button>
            </form>
        @else
            @if ($section === 'balances')<flux:button :href="route('provider.service.index')">Disconnection &amp; Reconnection</flux:button>@endif
            <form method="GET" class="flex flex-wrap items-end gap-3">
                <flux:input placeholder="Select a billing month" type="month" name="month" label="Billing month" :value="request('month')" />
                <flux:input placeholder="Search by consumer name or account number" name="search" label="Consumer name or account" :value="request('search')" />
                <flux:input placeholder="Start date" name="from" type="date" label="From" :value="request('from')" />
                <flux:input placeholder="End date" name="to" type="date" label="To" :value="request('to')" />
                @if ($section === 'balances')
                    <flux:select name="overdue" label="Balance status"><option value="0">All unpaid</option><option value="1" @selected(request('overdue') === '1')>Overdue only</option></flux:select>
                @endif
                <flux:button type="submit">Filter</flux:button>
            </form>
            @if ($section === 'reports')
                <flux:card>
                    <flux:text>Bills generated: {{ $billCount }}</flux:text>
                    <flux:text>Billed: ₱{{ bcadd($billedTotal, '0', 2) }}</flux:text>
                    <flux:text>Verified collections: ₱{{ $collected }}</flux:text>
                    <flux:text>Consumption: {{ $consumption }} m³</flux:text>
                </flux:card>
            @endif
            <flux:heading level="2">Outstanding: ₱{{ bcadd((string) $outstanding, '0', 2) }}</flux:heading>
            @foreach ($bills as $bill)
                @if ($section === 'reports' || bccomp($bill->balance(), '0', 2) > 0)
                    <flux:card><a href="{{ route('bills.print', $bill) }}">{{ $bill->consumer->name }} · {{ $bill->number }}</a><flux:text>{{ $bill->paymentStatus() }} · Due {{ $bill->due_at->format('M d, Y') }} · ₱{{ $bill->balance() }} remaining · Penalty ₱{{ $bill->late_fee }}</flux:text>
                    @if ($section === 'balances' && !$bill->late_fee_applied_at && bccomp($lateFee, '0', 2) > 0 && $bill->due_at->copy()->addDays($graceDays)->isBefore(today()))
                        <form method="POST" action="{{ route('provider.bills.penalty', $bill) }}">@csrf<flux:button type="submit">Apply eligible penalty</flux:button></form>
                    @elseif ($section === 'balances')
                        <flux:text>{{ $bill->late_fee_applied_at ? 'Penalty already applied.' : (bccomp($lateFee, '0', 2) <= 0 ? 'No late fee is configured.' : 'Penalty becomes available after the grace period.') }}</flux:text>
                    @endif
                    </flux:card>
                @endif
            @endforeach
            @if ($bills->isEmpty())<flux:text>No bills match these filters.</flux:text>@endif
            {{ $bills->links() }}
        @endif
    </div>
</x-layouts::app>
