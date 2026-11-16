<x-layouts::app title="Payments">
<div class="client-page w-full min-w-0 space-y-6">
<x-page-header title="Payments" description="Track active submissions and past payments. Pending submissions do not reduce your bill balance until the provider verifies them.">
    @if ($bills->isNotEmpty())
        <x-slot:actions>
            <flux:modal.trigger name="submit-payment">
                <flux:button variant="primary">Submit payment</flux:button>
            </flux:modal.trigger>
        </x-slot:actions>
    @endif
</x-page-header>
<form method="GET" class="flex flex-col gap-3 sm:flex-row sm:items-end">
<flux:select name="status" label="Payment status"><option value="">All payments</option>@foreach(['pending' => 'Active / awaiting verification', 'verified' => 'Confirmed', 'rejected' => 'Rejected'] as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</flux:select>
<flux:button type="submit">Filter payments</flux:button>
</form>
@forelse($payments as $payment)
<flux:card class="space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <flux:heading level="2" size="lg">{{ $payment->reference }} · <span class="tabular-nums">₱{{ $payment->amount }}</span></flux:heading>
        <flux:badge :color="match ($payment->status) { 'verified' => 'green', 'rejected' => 'red', default => 'zinc' }">{{ ucfirst($payment->status) }}</flux:badge>
    </div>
    <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">Payment date</dt><dd class="mt-1 font-medium">{{ $payment->paid_at->format('M d, Y') }}</dd></div>
        <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">Payment method</dt><dd class="mt-1 font-medium">{{ ucfirst(str_replace('_', ' ', $payment->method)) }}</dd></div>
        <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">Bill</dt><dd class="mt-1 font-medium">{{ $payment->coveredBills()->pluck('number')->implode(', ') }}</dd></div>
        <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">Billing periods</dt><dd class="mt-1 font-medium">{{ $payment->coveredBills()->map(fn ($bill) => $bill->period_month->format('M Y').' · '.$bill->connection->meter_number)->implode(', ') }}</dd></div>
    </dl>
    @if($payment->status === 'rejected')<flux:text>Reason: {{ $payment->remarks }}</flux:text>@endif
    <div class="flex flex-wrap gap-3">
        @if($payment->proof_path)<flux:button :href="route('portal.payments.proof', $payment)">View submitted proof</flux:button>@endif
        @if($payment->status === 'verified')<flux:button :href="route('payments.print', $payment)" data-document-preview>View payment receipt</flux:button>@endif
    </div>
</flux:card>
@empty <flux:text>No payments match this filter.</flux:text> @endforelse
{{ $payments->links() }}
@if($bills->isNotEmpty())
<x-form-modal name="submit-payment" title="Submit payment for verification" description="Your provider will verify the payment details and proof before applying the payment to your bill." :action="route('portal.payments.store')" submit-label="Submit payment" :upload="true" :open="request()->filled('bill')">
    @php
        $selectedBill = (string) filter_var(old('bill_id', request('bill', '')), FILTER_VALIDATE_INT);
        $paymentScope = old('payment_scope') === 'all' ? 'all' : 'single';
        $billBalances = $bills->mapWithKeys(fn ($bill) => [$bill->id => $bill->balance()]);
        $initialAmount = old('amount', $paymentScope === 'all' ? $allBillsTotal : ($billBalances[$selectedBill] ?? ''));
        $initialAmount = is_scalar($initialAmount) ? (string) $initialAmount : '';
    @endphp
    <div class="space-y-4" data-payment-form x-data="{ scope: @js($paymentScope), selectedBill: @js($selectedBill), amount: @js($initialAmount), balances: @js($billBalances), allTotal: @js($allBillsTotal), fillAmount() { this.amount = this.scope === 'all' ? this.allTotal : (this.balances[this.selectedBill] || '') } }">
    <flux:select name="payment_scope" label="Pay for" x-model="scope" x-on:change="fillAmount()">
        <option value="single" @selected($paymentScope === 'single')>One unpaid bill</option>
        @if ($allBills->count() >= 2 || $paymentScope === 'all')<option value="all" @selected($paymentScope === 'all')>Pay all unpaid bills</option>@endif
    </flux:select>
    <div x-show="scope === 'single'">
        <flux:select name="bill_id" label="Unpaid bill" x-model="selectedBill" x-on:change="fillAmount()" x-bind:required="scope === 'single'" x-bind:disabled="scope !== 'single'"><option value="">Choose a bill</option>@foreach($bills as $bill)<option value="{{ $bill->id }}" @selected($selectedBill == $bill->id)>{{ $bill->number }} · {{ $bill->period_month->format('M Y') }} · {{ $bill->connection->meter_number }} · ₱{{ $bill->balance() }} remaining</option>@endforeach</flux:select>
    </div>
    <div x-cloak x-show="scope === 'all'" class="space-y-2">
        <flux:heading level="3">Included unpaid bills ({{ $allBills->count() }})</flux:heading>
        <ul class="max-h-64 space-y-2 overflow-y-auto text-sm">
            @foreach ($allBills as $bill)<li>{{ $bill->number }} · {{ $bill->period_month->format('M Y') }} · {{ $bill->connection->meter_number }} · ₱{{ $bill->balance() }} remaining</li>@endforeach
        </ul>
        <flux:text>Total remaining: ₱{{ $allBillsTotal }}. This includes all meters and bills that are not yet due.</flux:text>
        <flux:text>A partial payment applies to the oldest billing month first. Bills from the same month apply in the order they were issued. Bills receiving no amount will not be included in the payment receipt.</flux:text>
    </div>
    <flux:input name="reference" label="Payment reference" placeholder="Enter the receipt or transaction reference" :value="is_string(old('reference')) ? old('reference') : null" required />
    <flux:input name="amount" label="Amount (PHP)" description="You can enter a smaller amount for a partial payment." placeholder="e.g. 250.00" :value="$initialAmount" x-model="amount" x-bind:max="scope === 'all' ? allTotal : balances[selectedBill]" type="number" step="0.01" min="0.01" required />
    <flux:input name="paid_at" label="Payment date" type="date" :value="is_string(old('paid_at')) ? old('paid_at') : null" :max="today()->toDateString()" required />
    <flux:input name="proof" label="Proof of payment" type="file" description="PDF, JPG or PNG, up to 5 MB. Reattach the file if validation fails." accept=".pdf,.jpg,.jpeg,.png" required />
    </div>
    <flux:text>The single bill selector shows the latest 50 unpaid bills. Open an older bill from My Bills to select it here. Pay all includes every unpaid bill.</flux:text>
</x-form-modal>
@else <flux:text>You have no unpaid bills available for payment submission.</flux:text> @endif
</div>
</x-layouts::app>
