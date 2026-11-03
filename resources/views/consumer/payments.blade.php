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
<article class="space-y-3 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
    <flux:heading level="2">{{ $payment->reference }} · ₱{{ $payment->amount }}</flux:heading>
    <flux:text>{{ ucfirst($payment->status) }} · {{ $payment->paid_at->format('M d, Y') }} · {{ ucfirst(str_replace('_', ' ', $payment->method)) }} · Bill {{ $payment->bill->number }}</flux:text>
    @if($payment->status === 'rejected')<flux:text>Reason: {{ $payment->remarks }}</flux:text>@endif
    <div class="flex flex-wrap gap-3">
        @if($payment->proof_path)<flux:button :href="route('portal.payments.proof', $payment)">View submitted proof</flux:button>@endif
        @if($payment->status === 'verified')<flux:button :href="route('payments.print', $payment)" data-document-preview>Print receipt</flux:button>@endif
    </div>
</article>
@empty <flux:text>No payments match this filter.</flux:text> @endforelse
{{ $payments->links() }}
@if($bills->isNotEmpty())
<x-form-modal name="submit-payment" title="Submit payment for verification" description="Your provider will verify the payment details and proof before applying the payment to your bill." :action="route('portal.payments.store')" submit-label="Submit payment" :upload="true" :open="request()->filled('bill')">
    <div class="space-y-4">
    <flux:select name="bill_id" label="Unpaid bill" required><option value="">Choose a bill</option>@foreach($bills as $bill)<option value="{{ $bill->id }}" @selected(old('bill_id', request('bill')) == $bill->id)>{{ $bill->number }} · ₱{{ $bill->balance() }} remaining</option>@endforeach</flux:select>
    <flux:input name="reference" label="Payment reference" placeholder="Enter the receipt or transaction reference" :value="old('reference')" required />
    <flux:input name="amount" label="Amount (PHP)" placeholder="e.g. 250.00" :value="old('amount')" type="number" step="0.01" min="0.01" required />
    <flux:input name="paid_at" label="Payment date" type="date" :value="old('paid_at')" :max="today()->toDateString()" required />
    <flux:input name="proof" label="Proof of payment" type="file" description="PDF, JPG or PNG, up to 5 MB. Reattach the file if validation fails." accept=".pdf,.jpg,.jpeg,.png" required />
    </div>
    <flux:text>Only the latest 50 unpaid bills appear here. To pay an older bill, open it from My Bills and select Submit payment details.</flux:text>
</x-form-modal>
@else <flux:text>You have no unpaid bills available for payment submission.</flux:text> @endif
</div>
</x-layouts::app>
