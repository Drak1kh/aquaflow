<x-layouts::app :title="$section === 'settings' ? 'Profile Settings' : ($section === 'balances' ? 'Outstanding Balance Management' : 'Reports')">
    <div class="provider-page w-full min-w-0 space-y-6">
        <x-page-header :title="$section === 'settings' ? 'Profile Settings' : ($section === 'balances' ? 'Outstanding Balance Management' : 'Reports')" />


        @if ($section === 'settings')
            <form method="POST" action="{{ route('provider.profile') }}" class="space-y-4">
                @csrf @method('PUT')
                @foreach (['name' => ['Provider name', 'text'], 'address' => ['Office address', 'text'], 'phone' => ['Contact number', 'tel'], 'email' => ['Contact email', 'email'], 'due_days' => ['Due days after month end', 'number'], 'grace_days' => ['Grace period (days)', 'number'], 'late_fee' => ['Late fee (PHP)', 'number']] as $field => [$label, $type])
                    <flux:input :placeholder="match ($field) { 'name' => 'Enter the provider name', 'address' => 'Enter the office address', 'phone' => 'e.g. 09123456789', 'email' => 'e.g. office@example.com', 'due_days' => 'e.g. 14', 'grace_days' => 'e.g. 7', 'late_fee' => 'e.g. 20.00' }" :name="$field" :value="old($field, $provider->$field)" :label="$label" :type="$type" :inputmode="$field === 'phone' ? 'numeric' : null" :pattern="$field === 'phone' ? '[0-9]*' : null" :min="$type === 'number' ? 0 : null" :step="$field === 'late_fee' ? '0.01' : ($type === 'number' ? '1' : null)" :required="in_array($field, ['name', 'due_days'])" />
                @endforeach
                <flux:text>Due days are counted after the end of the billing month. Water rates are configured under Billing.</flux:text>
                <flux:button type="submit" variant="primary">Save settings</flux:button>
            </form>
        @else
            @if ($section === 'balances')
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <flux:text>Review unpaid bills before starting a service action. Balances shown here are bill-specific.</flux:text>
                    <flux:button :href="route('provider.service.index')">Disconnection &amp; Reconnection</flux:button>
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <flux:card><flux:text>Outstanding balance</flux:text><flux:heading class="tabular-nums" level="2">₱{{ bcadd((string) $outstanding, '0', 2) }}</flux:heading></flux:card>
                    <flux:card><flux:text>Unpaid bills</flux:text><flux:heading class="tabular-nums" level="2">{{ $billCount }}</flux:heading></flux:card>
                    <flux:card><flux:text>Overdue bills</flux:text><flux:heading class="tabular-nums" level="2">{{ $overdueBillCount }}</flux:heading></flux:card>
                </div>
            @endif
            <form method="GET" class="flex flex-wrap items-end gap-3">
                <flux:input name="month" type="month" label="Billing month" :value="request('month')" />
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
            @if ($section === 'balances')
                <flux:heading level="2">Bills requiring attention</flux:heading>
            @else
                <flux:heading level="2">Outstanding: ₱{{ bcadd((string) $outstanding, '0', 2) }}</flux:heading>
            @endif
            @foreach ($bills as $bill)
                @if ($section === 'reports' || bccomp($bill->balance(), '0', 2) > 0)
                    @if ($section === 'balances')
                        @php($paymentStatus = $bill->paymentStatus())
                        @php($statusColor = match ($paymentStatus) { 'Overdue' => 'red', 'Partially Paid' => 'yellow', 'Unpaid' => 'zinc', default => 'zinc' })
                        <flux:card class="overflow-x-auto w-fit max-w-full p-0">
                            <div class="flex min-w-max divide-x divide-zinc-200 dark:divide-zinc-700">
                                <div class="w-48 px-4 py-3"><flux:text class="text-xs font-medium uppercase tracking-wide">{{ __('Name') }}</flux:text><flux:text class="truncate font-medium">{{ $bill->consumer->name }}</flux:text></div>
                                <div class="w-32 px-4 py-3"><flux:text class="text-xs font-medium uppercase tracking-wide">{{ __('Account') }}</flux:text><flux:text>{{ $bill->consumer->account_number }}</flux:text></div>
                                <div class="w-32 px-4 py-3"><flux:text class="text-xs font-medium uppercase tracking-wide">{{ __('Meter') }}</flux:text><flux:text>{{ $bill->connection?->meter_number ?? '—' }}</flux:text></div>
                                <div class="w-32 px-4 py-3"><flux:text class="text-xs font-medium uppercase tracking-wide">{{ __('Bill') }}</flux:text><a href="{{ route('bills.print', $bill) }}" data-document-preview class="font-medium text-brand-primary focus-visible:outline-2 focus-visible:outline-offset-2 dark:text-brand-aqua">{{ $bill->number }}</a></div>
                                <div class="w-36 px-4 py-3"><flux:text class="text-xs font-medium uppercase tracking-wide">{{ __('Due date') }}</flux:text><flux:text>{{ $bill->due_at->format('M d, Y') }}</flux:text></div>
                                <div class="w-36 px-4 py-3"><flux:text class="text-xs font-medium uppercase tracking-wide">{{ __('Remaining') }}</flux:text><flux:text class="font-medium tabular-nums">₱{{ $bill->balance() }}</flux:text></div>
                                <div class="w-32 px-4 py-3"><flux:text class="text-xs font-medium uppercase tracking-wide">{{ __('Penalty') }}</flux:text><flux:text class="tabular-nums">₱{{ $bill->late_fee }}</flux:text>
                                    @if (!$bill->late_fee_applied_at && bccomp($lateFee, '0', 2) > 0 && $bill->due_at->copy()->addDays($graceDays)->isBefore(today()))
                                        <form class="mt-2" method="POST" action="{{ route('provider.bills.penalty', $bill) }}">@csrf<flux:button type="submit">{{ __('Apply eligible penalty') }}</flux:button></form>
                                    @endif
                                </div>
                                <div class="w-32 px-4 py-3"><flux:text class="text-xs font-medium uppercase tracking-wide">{{ __('Status') }}</flux:text><flux:badge :color="$statusColor">{{ $paymentStatus }}</flux:badge></div>
                            </div>
                        </flux:card>
                    @else
                        <flux:card class="space-y-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <flux:heading level="2">{{ $bill->consumer->name }}</flux:heading>
                                    <flux:text>Account {{ $bill->consumer->account_number }} · Meter {{ $bill->connection?->meter_number ?? '—' }}</flux:text>
                                </div>
                                <flux:badge>{{ ucfirst(str_replace('_', ' ', $bill->paymentStatus())) }}</flux:badge>
                            </div>
                            <div class="grid gap-3 text-sm sm:grid-cols-4">
                                <div><flux:text>Bill</flux:text><a href="{{ route('bills.print', $bill) }}" data-document-preview class="text-brand-primary focus-visible:outline-2 focus-visible:outline-offset-2 dark:text-brand-aqua">{{ $bill->number }}</a></div>
                                <div><flux:text>Due date</flux:text><flux:text>{{ $bill->due_at->format('M d, Y') }}</flux:text></div>
                                <div><flux:text>Remaining</flux:text><flux:text class="tabular-nums">₱{{ $bill->balance() }}</flux:text></div>
                                <div><flux:text>Penalty ₱{{ $bill->late_fee }}</flux:text></div>
                            </div>
                        </flux:card>
                    @endif
                @endif
            @endforeach
            @if ($bills->isEmpty())<flux:text>No bills match these filters.</flux:text>@endif
            {{ $bills->links() }}
        @endif
    </div>
</x-layouts::app>
