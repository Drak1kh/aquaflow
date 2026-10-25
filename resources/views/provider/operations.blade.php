<x-layouts::app :title="__('Provider operations')">
    <div class="mx-auto flex max-w-7xl flex-col gap-8">
        <x-page-header :title="$provider?->name ?? __('Water provider')" :description="__('Manage consumer accounts, readings, bills, payments, and support.')" />

        @if (! $provider)
            <flux:callout variant="warning">{{ __('This account has not been assigned to a water provider. Contact a Super Admin.') }}</flux:callout>
        @endif

        @can('permission', 'consumers.view')
            <section id="consumers" class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3"><flux:heading size="lg">{{ __('Consumers') }}</flux:heading><flux:badge color="blue">{{ $consumers->count() }}</flux:badge></div>
                    @can('permission', 'consumers.manage')
                        <flux:modal.trigger name="create-consumer"><flux:button variant="primary" icon="plus">{{ __('Register consumer') }}</flux:button></flux:modal.trigger>
                    @endcan
                </div>
                <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                    <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
                        <thead class="bg-[#F7FCFE] text-[#062E68] dark:bg-zinc-800 dark:text-white"><tr><th class="px-4 py-3">{{ __('Account') }}</th><th class="px-4 py-3">{{ __('Consumer') }}</th><th class="px-4 py-3">{{ __('Portal') }}</th><th class="px-4 py-3">{{ __('Status') }}</th><th class="px-4 py-3">{{ __('Actions') }}</th></tr></thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse ($consumers as $consumer)
                                <tr>
                                    <td class="px-4 py-3">{{ $consumer->account_number }}</td>
                                    <td class="px-4 py-3">{{ $consumer->name }}</td>
                                    <td class="px-4 py-3">{{ $consumer->portalUser ? __('Active') : __('Not activated') }}</td>
                                    <td class="px-4 py-3">{{ $consumer->is_active ? __('Active') : __('Inactive') }}</td>
                                    <td class="px-4 py-3">
                                        @can('permission', 'consumers.manage')
                                            <div class="flex flex-wrap gap-2">
                                                @if (! $consumer->portalUser)
                                                    <flux:modal.trigger :name="'activate-portal-'.$consumer->id"><flux:button size="sm" variant="subtle">{{ __('Activate portal') }}</flux:button></flux:modal.trigger>
                                                @endif
                                                <form method="POST" action="{{ route('provider.consumers.status', $consumer) }}">
                                                    @csrf @method('PATCH')
                                                    <flux:button size="sm" type="submit">{{ $consumer->is_active ? __('Deactivate') : __('Activate') }}</flux:button>
                                                </form>
                                            </div>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-8 text-center">{{ __('Register a consumer to begin.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @can('permission', 'consumers.manage')
                    <x-form-modal name="create-consumer" :title="__('Register consumer')" :action="route('provider.consumers.store')" :submit-label="__('Register consumer')">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:input name="account_number" :value="old('_form') === 'create-consumer' ? old('account_number') : null" :label="__('Account number')" required />
                            <flux:input name="name" :value="old('_form') === 'create-consumer' ? old('name') : null" :label="__('Full name')" required />
                            <flux:input name="email" type="email" :value="old('_form') === 'create-consumer' ? old('email') : null" :label="__('Email')" />
                            <flux:input name="phone" :value="old('_form') === 'create-consumer' ? old('phone') : null" :label="__('Phone')" />
                            <div class="sm:col-span-2"><flux:input name="address" :value="old('_form') === 'create-consumer' ? old('address') : null" :label="__('Service address')" required /></div>
                        </div>
                    </x-form-modal>
                    @foreach ($consumers->whereNull('portalUser') as $consumer)
                        <x-form-modal :name="'activate-portal-'.$consumer->id" :title="__('Activate portal access')" :description="$consumer->name.' · '.$consumer->account_number" :action="route('provider.consumers.portal', $consumer)" :submit-label="__('Activate portal')">
                            <div class="space-y-4">
                                <flux:input name="email" type="email" :value="old('_form') === 'activate-portal-'.$consumer->id ? old('email') : $consumer->email" :label="__('Login email')" required />
                                <flux:input name="password" type="password" :label="__('Temporary password')" required autocomplete="new-password" />
                            </div>
                        </x-form-modal>
                    @endforeach
                @endcan
            </section>
        @endcan

        @can('permission', 'connections.view')
            <section id="connections" class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <flux:heading size="lg">{{ __('Connections and readings') }}</flux:heading>
                    @can('permission', 'connections.manage')
                        <div class="flex flex-wrap gap-2">
                            <flux:modal.trigger name="create-connection"><flux:button icon="plus">{{ __('Add connection') }}</flux:button></flux:modal.trigger>
                            <flux:modal.trigger name="create-reading"><flux:button variant="primary">{{ __('Record reading') }}</flux:button></flux:modal.trigger>
                        </div>
                    @endcan
                </div>
                <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                    <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
                        <thead class="bg-[#F7FCFE] text-[#062E68] dark:bg-zinc-800 dark:text-white"><tr><th class="px-4 py-3">{{ __('Connection') }}</th><th class="px-4 py-3">{{ __('Consumer') }}</th><th class="px-4 py-3">{{ __('Meter') }}</th><th class="px-4 py-3">{{ __('Status') }}</th></tr></thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse ($connections as $connection)
                                <tr><td class="px-4 py-3">{{ $connection->number }}</td><td class="px-4 py-3">{{ $connection->consumer->name }}</td><td class="px-4 py-3">{{ $connection->meter_number }}</td><td class="px-4 py-3">{{ ucfirst($connection->status) }}</td></tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-8 text-center">{{ __('No connections yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @can('permission', 'connections.manage')
                    <x-form-modal name="create-connection" :title="__('Add connection')" :action="route('provider.connections.store')" :submit-label="__('Add connection')">
                        <div class="space-y-4">
                            <flux:select name="consumer_id" :label="__('Consumer')" required>
                                <option value="">{{ __('Choose consumer') }}</option>
                                @foreach ($consumers as $consumer)
                                    <option value="{{ $consumer->id }}" @selected(old('_form') === 'create-connection' && old('consumer_id') == $consumer->id)>{{ $consumer->name }}</option>
                                @endforeach
                            </flux:select>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <flux:input name="number" :value="old('_form') === 'create-connection' ? old('number') : null" :label="__('Connection number')" required />
                                <flux:input name="meter_number" :value="old('_form') === 'create-connection' ? old('meter_number') : null" :label="__('Meter number')" required />
                            </div>
                            <flux:input name="installed_at" type="date" :value="old('_form') === 'create-connection' ? old('installed_at') : null" :label="__('Installation date')" />
                        </div>
                    </x-form-modal>
                    <x-form-modal name="create-reading" :title="__('Record meter reading')" :action="route('provider.readings.store')" :submit-label="__('Save reading')">
                        <div class="space-y-4">
                            <flux:select name="connection_id" :label="__('Connection')" required>
                                <option value="">{{ __('Choose connection') }}</option>
                                @foreach ($connections as $connection)
                                    <option value="{{ $connection->id }}" @selected(old('_form') === 'create-reading' && old('connection_id') == $connection->id)>{{ $connection->number }} · {{ $connection->consumer->name }}</option>
                                @endforeach
                            </flux:select>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <flux:input name="period_month" type="month" :value="old('_form') === 'create-reading' ? old('period_month') : null" :label="__('Billing month')" required />
                                <flux:input name="current_reading" type="number" step="0.001" min="0" :value="old('_form') === 'create-reading' ? old('current_reading') : null" :label="__('Current meter reading')" required />
                            </div>
                        </div>
                    </x-form-modal>
                @endcan
            </section>
        @endcan

        @can('permission', 'billing.view')
            <section id="billing" class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <flux:heading size="lg">{{ __('Billing') }}</flux:heading>
                    @can('permission', 'billing.manage')
                        <div class="flex flex-wrap gap-2">
                            <flux:modal.trigger name="create-water-rate"><flux:button>{{ __('Set water rate') }}</flux:button></flux:modal.trigger>
                            <flux:modal.trigger name="create-bill"><flux:button variant="primary">{{ __('Generate bill') }}</flux:button></flux:modal.trigger>
                            <flux:modal.trigger name="create-batch-bills"><flux:button>{{ __('Generate monthly batch') }}</flux:button></flux:modal.trigger>
                        </div>
                    @endcan
                </div>
                @can('permission', 'billing.manage')
                    <form method="GET" action="{{ route('dashboard.provider') }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                        <flux:select name="connection" :label="__('Connection to preview')"><option value="">{{ __('Choose connection') }}</option>
                            @foreach ($connections as $connection)
                                <option value="{{ $connection->id }}" @selected(request('connection') == $connection->id)>{{ $connection->number }}</option>
                            @endforeach
                        </flux:select>
                        <flux:input name="month" type="month" :value="request('month')" :label="__('Month')" required />
                        <flux:button type="submit">{{ __('Preview bill') }}</flux:button>
                        @if ($preview)
                            <div class="w-full rounded-lg bg-[#F7FCFE] p-3 text-sm text-[#062E68] dark:bg-zinc-800 dark:text-white">{{ __('Consumption') }}: {{ $preview['consumption'] }} m³ · {{ __('Total') }}: ₱{{ $preview['total'] }}</div>
                        @endif
                    </form>
                @endcan
                <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                    <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
                        <thead class="bg-[#F7FCFE] text-[#062E68] dark:bg-zinc-800 dark:text-white"><tr><th class="px-4 py-3">{{ __('Bill') }}</th><th class="px-4 py-3">{{ __('Consumer') }}</th><th class="px-4 py-3">{{ __('Month') }}</th><th class="px-4 py-3 text-right">{{ __('Total') }}</th><th class="px-4 py-3 text-right">{{ __('Balance') }}</th></tr></thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse ($bills as $bill)
                                <tr><td class="px-4 py-3">{{ $bill->number }}</td><td class="px-4 py-3">{{ $bill->consumer->name }}</td><td class="px-4 py-3">{{ $bill->period_month->format('M Y') }}</td><td class="px-4 py-3 text-right tabular-nums">₱{{ number_format((float) $bill->total, 2) }}</td><td class="px-4 py-3 text-right tabular-nums">₱{{ number_format((float) $bill->balance(), 2) }}</td></tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-8 text-center">{{ __('No bills generated yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @can('permission', 'billing.manage')
                    <x-form-modal name="create-water-rate" :title="__('Set water rate')" :action="route('provider.rates.store')" :submit-label="__('Save rate')">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:input name="base_charge" type="number" step="0.01" min="0" :value="old('_form') === 'create-water-rate' ? old('base_charge') : null" :label="__('Base charge (PHP)')" required />
                            <flux:input name="unit_rate" type="number" step="0.01" min="0" :value="old('_form') === 'create-water-rate' ? old('unit_rate') : null" :label="__('Per m³ (PHP)')" required />
                            <flux:input name="effective_from" type="date" :value="old('_form') === 'create-water-rate' ? old('effective_from') : null" :label="__('Effective from')" required />
                        </div>
                    </x-form-modal>
                    <x-form-modal name="create-bill" :title="__('Generate monthly bill')" :action="route('provider.bills.store')" :submit-label="__('Generate bill')">
                        <div class="space-y-4">
                            <flux:select name="connection_id" :label="__('Connection')" required>
                                <option value="">{{ __('Choose connection') }}</option>
                                @foreach ($connections as $connection)
                                    <option value="{{ $connection->id }}" @selected((old('_form') === 'create-bill' ? old('connection_id') : request('connection')) == $connection->id)>{{ $connection->number }}</option>
                                @endforeach
                            </flux:select>
                            <flux:input name="period_month" type="month" :value="old('_form') === 'create-bill' ? old('period_month') : request('month')" :label="__('Month')" required />
                        </div>
                    </x-form-modal>
                    <x-form-modal name="create-batch-bills" :title="__('Generate monthly batch')" :description="__('Active connections with a reading for the month will be billed. Existing bills are skipped.')" :action="route('provider.bills.batch')" :submit-label="__('Generate monthly batch')">
                        <flux:input name="period_month" type="month" :value="old('_form') === 'create-batch-bills' ? old('period_month') : null" :label="__('Month')" required />
                    </x-form-modal>
                @endcan
            </section>
        @endcan

        @can('permission', 'payments.view')
            <section id="payments" class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <flux:heading size="lg">{{ __('Payments') }}</flux:heading>
                    @can('permission', 'payments.manage')
                        <flux:modal.trigger name="create-payment"><flux:button variant="primary" icon="plus">{{ __('Record payment') }}</flux:button></flux:modal.trigger>
                    @endcan
                </div>
                <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                    <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
                        <thead class="bg-[#F7FCFE] text-[#062E68] dark:bg-zinc-800 dark:text-white"><tr><th class="px-4 py-3">{{ __('Reference') }}</th><th class="px-4 py-3">{{ __('Consumer') }}</th><th class="px-4 py-3 text-right">{{ __('Amount') }}</th><th class="px-4 py-3">{{ __('Status') }}</th><th class="px-4 py-3">{{ __('Action') }}</th></tr></thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse ($payments as $payment)
                                <tr>
                                    <td class="px-4 py-3">{{ $payment->reference }}</td>
                                    <td class="px-4 py-3">{{ $payment->consumer->name }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">₱{{ number_format((float) $payment->amount, 2) }}</td>
                                    <td class="px-4 py-3">{{ ucfirst($payment->status) }}</td>
                                    <td class="px-4 py-3">
                                        @if ($payment->status === 'pending')
                                            @can('permission', 'payments.verify')
                                                <div class="flex flex-wrap gap-2">
                                                    <form method="POST" action="{{ route('provider.payments.verify', $payment) }}">@csrf<flux:button size="sm" type="submit">{{ __('Verify') }}</flux:button></form>
                                                    <flux:modal.trigger :name="'reject-payment-'.$payment->id"><flux:button size="sm" variant="danger">{{ __('Reject') }}</flux:button></flux:modal.trigger>
                                                </div>
                                            @endcan
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-8 text-center">{{ __('No payments recorded yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @can('permission', 'payments.manage')
                    <x-form-modal name="create-payment" :title="__('Record payment')" :description="__('Payments remain pending until verified.')" :action="route('provider.payments.store')" :submit-label="__('Record payment')" :upload="true">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:select name="bill_id" :label="__('Bill')" required>
                                <option value="">{{ __('Choose bill') }}</option>
                                @foreach ($bills as $bill)
                                    <option value="{{ $bill->id }}" @selected(old('_form') === 'create-payment' && old('bill_id') == $bill->id)>{{ $bill->number }} · {{ $bill->consumer->name }}</option>
                                @endforeach
                            </flux:select>
                            <flux:input name="reference" :value="old('_form') === 'create-payment' ? old('reference') : null" :label="__('Reference')" required />
                            <flux:input name="amount" type="number" step="0.01" min="0.01" :value="old('_form') === 'create-payment' ? old('amount') : null" :label="__('Amount (PHP)')" required />
                            <flux:select name="method" :label="__('Method')">
                                @foreach (['cash', 'bank_transfer', 'e_wallet', 'other'] as $method)
                                    <option value="{{ $method }}" @selected(old('_form') === 'create-payment' && old('method') === $method)>{{ ucfirst(str_replace('_', ' ', $method)) }}</option>
                                @endforeach
                            </flux:select>
                            <flux:input name="paid_at" type="date" :value="old('_form') === 'create-payment' ? old('paid_at') : null" :label="__('Payment date')" required />
                            <flux:input name="proof" type="file" :label="__('Proof (optional)')" />
                        </div>
                    </x-form-modal>
                @endcan
                @can('permission', 'payments.verify')
                    @foreach ($payments->where('status', 'pending') as $payment)
                        <x-form-modal :name="'reject-payment-'.$payment->id" :title="__('Reject payment')" :description="$payment->reference" :action="route('provider.payments.reject', $payment)" :submit-label="__('Reject payment')" variant="danger">
                            <flux:textarea name="remarks" :label="__('Reason for rejection')" required>{{ old('_form') === 'reject-payment-'.$payment->id ? old('remarks') : '' }}</flux:textarea>
                        </x-form-modal>
                    @endforeach
                @endcan
            </section>
        @endcan

        @can('permission', 'tickets.view')
            <section id="tickets" class="space-y-4">
                <flux:heading size="lg">{{ __('Support tickets') }}</flux:heading>
                <div class="space-y-3">
                    @forelse ($tickets as $ticket)
                        <details class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                            <summary class="cursor-pointer font-medium">{{ $ticket->subject }} <span class="text-sm font-normal text-zinc-500">· {{ $ticket->consumer->name }} · {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</span></summary>
                            <p class="mt-3 whitespace-pre-wrap text-sm">{{ $ticket->description }}</p>
                            @can('permission', 'tickets.respond')
                                <div class="mt-4 flex flex-wrap gap-3">
                                    @if ($ticket->status !== 'closed')
                                        <flux:modal.trigger :name="'reply-ticket-'.$ticket->id"><flux:button>{{ __('Reply') }}</flux:button></flux:modal.trigger>
                                    @endif
                                    @if (! $ticket->escalated_at)
                                        <form method="POST" action="{{ route('provider.tickets.escalate', $ticket) }}">@csrf<flux:button type="submit" variant="danger">{{ __('Escalate') }}</flux:button></form>
                                    @endif
                                </div>
                            @endcan
                        </details>
                    @empty
                        <div class="rounded-xl border border-zinc-200 p-6 text-sm dark:border-zinc-700">{{ __('Consumer support tickets will appear here.') }}</div>
                    @endforelse
                </div>
                @can('permission', 'tickets.respond')
                    @foreach ($tickets->where('status', '!=', 'closed') as $ticket)
                        <x-form-modal :name="'reply-ticket-'.$ticket->id" :title="__('Reply to ticket')" :description="$ticket->subject" :action="route('provider.tickets.reply', $ticket)" :submit-label="__('Send reply')" :upload="true">
                            <div class="space-y-4">
                                <flux:textarea name="body" :label="__('Message')" required>{{ old('_form') === 'reply-ticket-'.$ticket->id ? old('body') : '' }}</flux:textarea>
                                <flux:input name="attachment" type="file" :label="__('Attachment (PDF or image, up to 5 MB)')" />
                            </div>
                        </x-form-modal>
                    @endforeach
                @endcan
            </section>
        @endcan
    </div>
</x-layouts::app>
