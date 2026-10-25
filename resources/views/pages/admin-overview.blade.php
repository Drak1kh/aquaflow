<x-layouts::app :title="__('Platform oversight')">
    <main class="flex w-full min-w-0 flex-col gap-8 py-8 text-[#062E68] dark:text-white">
        <header><flux:heading size="xl">Platform oversight</flux:heading><flux:text>Review billing, payments, reported issues, and audit history across providers.</flux:text></header>
        @include('partials.aquaflow-alerts')
        <flux:card><form method="GET" action="{{ route('admin.overview') }}" class="flex flex-wrap items-end gap-3">
            <flux:select name="water_provider_id" label="Provider"><flux:select.option value="">All providers</flux:select.option>@foreach ($providers as $provider)<flux:select.option value="{{ $provider->id }}" :selected="($filters['water_provider_id'] ?? null) == $provider->id">{{ $provider->name }}</flux:select.option>@endforeach</flux:select>
            <flux:input name="from" label="From" type="date" value="{{ $filters['from'] ?? '' }}" />
            <flux:input name="to" label="To" type="date" value="{{ $filters['to'] ?? '' }}" />
            <flux:select name="billing_period_id" label="Billing period">
                <flux:select.option value="">All periods</flux:select.option>
                @foreach ($billingPeriods as $period)
                    <flux:select.option :value="$period->id" :selected="($filters['billing_period_id'] ?? null) == $period->id">{{ $period->provider->name }} · {{ $period->label }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select name="payment_method" label="Payment method">
                <flux:select.option value="">All methods</flux:select.option>
                @foreach (['cash' => 'Cash', 'bank_transfer' => 'Bank transfer', 'e_wallet' => 'E-wallet', 'other' => 'Other'] as $value => $label)
                    <flux:select.option :value="$value" :selected="($filters['payment_method'] ?? '') === $value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select name="payment_status" label="Payment status">
                <flux:select.option value="">All statuses</flux:select.option>
                @foreach (['pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected', 'reversed' => 'Reversed'] as $value => $label)
                    <flux:select.option :value="$value" :selected="($filters['payment_status'] ?? '') === $value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:button type="submit" variant="primary">Apply filters</flux:button>
        </form></flux:card>
        <p class="text-sm text-slate-600 dark:text-slate-300">Billing period filters bills; payment method and status filter payments. Provider and dates apply to both.</p>
        <div class="flex gap-4 text-sm"><a class="text-[#079EE9] underline" href="{{ route('admin.overview.export', [...$filters, 'type' => 'bills']) }}">Download bills CSV</a><a class="text-[#079EE9] underline" href="{{ route('admin.overview.export', [...$filters, 'type' => 'payments']) }}">Download payments CSV</a></div>
        <div class="grid gap-6 md:grid-cols-3"><flux:card><flux:text>Bills</flux:text><flux:heading size="xl">{{ $billCount }}</flux:heading></flux:card><flux:card><flux:text>Billed</flux:text><flux:heading size="xl">{{ \App\DecimalAmount::format($billedCents) }}</flux:heading></flux:card><flux:card><flux:text>Verified collections</flux:text><flux:heading size="xl">{{ \App\DecimalAmount::format($collectedCents) }}</flux:heading></flux:card></div>
        <flux:card class="space-y-4"><div class="flex flex-wrap items-end justify-between gap-3"><div><flux:heading>Provider performance</flux:heading><flux:text>All-time totals; use Platform reports for date-filtered performance.</flux:text></div><flux:button :href="route('admin.reports')" wire:navigate>Open reports</flux:button></div><div class="overflow-x-auto"><flux:table><flux:table.columns><flux:table.column>Provider</flux:table.column><flux:table.column>Consumers</flux:table.column><flux:table.column>Bills</flux:table.column><flux:table.column>Billed</flux:table.column><flux:table.column>Collected</flux:table.column></flux:table.columns><flux:table.rows>
            @foreach ($providers as $provider)<flux:table.row><flux:table.cell>{{ $provider->name }}</flux:table.cell><flux:table.cell>{{ $provider->consumers_count }}</flux:table.cell><flux:table.cell>{{ $provider->bills_count }}</flux:table.cell><flux:table.cell>{{ $provider->billed_total ?? '0.00' }}</flux:table.cell><flux:table.cell>{{ $provider->collected_total ?? '0.00' }}</flux:table.cell></flux:table.row>@endforeach
        </flux:table.rows></flux:table></div></flux:card>
        <flux:card id="billing-oversight" class="space-y-4"><flux:heading size="lg">Billing oversight</flux:heading><div class="overflow-x-auto"><flux:table><flux:table.columns><flux:table.column>Number</flux:table.column><flux:table.column>Provider</flux:table.column><flux:table.column>Consumer</flux:table.column><flux:table.column>Amount</flux:table.column><flux:table.column>Status</flux:table.column></flux:table.columns><flux:table.rows>
            @foreach ($bills as $bill)<flux:table.row><flux:table.cell>{{ $bill->bill_number }}</flux:table.cell><flux:table.cell>{{ $bill->provider->name }}</flux:table.cell><flux:table.cell>{{ $bill->consumer->name }}</flux:table.cell><flux:table.cell>{{ $bill->total_amount }}</flux:table.cell><flux:table.cell>{{ $bill->status }}</flux:table.cell></flux:table.row>@endforeach
        </flux:table.rows></flux:table></div>{{ $bills->links() }}</flux:card>
        <div class="grid gap-6 xl:grid-cols-3">
            <flux:card class="space-y-3"><flux:heading>Bill adjustments</flux:heading>
                @forelse ($adjustments as $adjustment)
                    <p class="border-t border-[#BDECF8] pt-3 text-sm dark:border-zinc-700"><strong>{{ $adjustment->bill->bill_number }}</strong> · {{ $adjustment->bill->consumer->name }} · {{ $adjustment->amount }}<br><span class="text-slate-600 dark:text-slate-300">{{ $adjustment->reason }}</span></p>
                @empty <flux:text>No adjustments in this period.</flux:text> @endforelse
            </flux:card>
            <flux:card class="space-y-3"><flux:heading>Void bills to review</flux:heading>
                @forelse ($voidBills as $bill)
                    <p class="border-t border-[#BDECF8] pt-3 text-sm dark:border-zinc-700"><strong>{{ $bill->bill_number }}</strong> · {{ $bill->consumer->name }} · {{ $bill->total_amount }}</p>
                @empty <flux:text>No void bills.</flux:text> @endforelse
            </flux:card>
            <flux:card class="space-y-3"><div class="flex items-center justify-between gap-3"><flux:heading>Reported billing issues</flux:heading><a href="{{ route('admin.support', ['type' => 'billing']) }}" wire:navigate class="text-sm text-[#0869CF] dark:text-cyan-300">Review tickets</a></div>
                @forelse ($billingIssues as $ticket)
                    <p class="border-t border-[#BDECF8] pt-3 text-sm dark:border-zinc-700"><strong>#{{ $ticket->id }} {{ $ticket->subject }}</strong><br><span class="text-slate-600 dark:text-slate-300">{{ $ticket->consumer->name }} · {{ str_replace('_', ' ', $ticket->status) }}</span></p>
                @empty <flux:text>No escalated billing issues.</flux:text> @endforelse
            </flux:card>
        </div>
        <flux:card id="payment-oversight" class="space-y-4"><flux:heading size="lg">Payment oversight</flux:heading><div class="overflow-x-auto"><flux:table><flux:table.columns><flux:table.column>Reference</flux:table.column><flux:table.column>Consumer</flux:table.column><flux:table.column>Amount</flux:table.column><flux:table.column>Method</flux:table.column><flux:table.column>Status</flux:table.column><flux:table.column>Verified by</flux:table.column></flux:table.columns><flux:table.rows>
            @foreach ($payments as $payment)<flux:table.row><flux:table.cell>{{ $payment->reference_number }}</flux:table.cell><flux:table.cell>{{ $payment->consumer->name }}</flux:table.cell><flux:table.cell>{{ $payment->amount }}</flux:table.cell><flux:table.cell>{{ str_replace('_', ' ', $payment->method) }}</flux:table.cell><flux:table.cell>{{ $payment->status }}</flux:table.cell><flux:table.cell>{{ $payment->verifier?->name ?? '—' }}</flux:table.cell></flux:table.row>@endforeach
        </flux:table.rows></flux:table></div>{{ $payments->links() }}</flux:card>
        <div class="grid gap-6 lg:grid-cols-2">
            <flux:card class="space-y-3"><flux:heading>Rejected and reversed payments</flux:heading>
                @forelse ($paymentExceptions as $payment)
                    <p class="border-t border-[#BDECF8] pt-3 text-sm dark:border-zinc-700"><strong>{{ $payment->reference_number }}</strong> · {{ $payment->consumer->name }} · {{ $payment->amount }} · {{ ucfirst($payment->status) }}<br><span class="text-slate-600 dark:text-slate-300">{{ $payment->remarks ?: 'No remarks recorded' }}</span></p>
                @empty <flux:text>No rejected or reversed payments.</flux:text> @endforelse
            </flux:card>
            <flux:card class="space-y-3"><div class="flex items-center justify-between gap-3"><flux:heading>Reported payment discrepancies</flux:heading><a href="{{ route('admin.support', ['type' => 'payment']) }}" wire:navigate class="text-sm text-[#0869CF] dark:text-cyan-300">Review tickets</a></div>
                @forelse ($paymentIssues as $ticket)
                    <p class="border-t border-[#BDECF8] pt-3 text-sm dark:border-zinc-700"><strong>#{{ $ticket->id }} {{ $ticket->subject }}</strong><br><span class="text-slate-600 dark:text-slate-300">{{ $ticket->consumer->name }} · {{ str_replace('_', ' ', $ticket->status) }}</span></p>
                @empty <flux:text>No escalated payment issues.</flux:text> @endforelse
            </flux:card>
        </div>
        <flux:card class="space-y-4"><flux:heading>Recent activity</flux:heading><div class="overflow-x-auto"><flux:table><flux:table.columns><flux:table.column>When</flux:table.column><flux:table.column>Provider ID</flux:table.column><flux:table.column>Action</flux:table.column><flux:table.column>Record</flux:table.column></flux:table.columns><flux:table.rows>
            @foreach ($audits as $audit)<flux:table.row><flux:table.cell>{{ $audit->created_at }}</flux:table.cell><flux:table.cell>{{ $audit->water_provider_id ?? 'Platform' }}</flux:table.cell><flux:table.cell>{{ $audit->action }}</flux:table.cell><flux:table.cell>{{ $audit->subject_type }} #{{ $audit->subject_id }}</flux:table.cell></flux:table.row>@endforeach
        </flux:table.rows></flux:table></div></flux:card>
    </main>
</x-layouts::app>
