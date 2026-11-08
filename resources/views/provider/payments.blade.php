<x-layouts::app title="Payment Management">
<div class="provider-page w-full min-w-0 space-y-6">
<x-page-header title="Payment Management" />

<div class="grid gap-4 sm:grid-cols-2">
    <flux:card><flux:text>Pending review</flux:text><flux:heading class="tabular-nums" level="2">{{ $pendingPaymentCount }}</flux:heading><flux:text>Only verified payments reduce the account balance.</flux:text></flux:card>
    @if ($selectedBill)
        <flux:card><flux:text>Selected bill remaining</flux:text><flux:heading class="tabular-nums" level="2">₱{{ number_format((float) $selectedBill->balance(), 2) }}</flux:heading><flux:text>{{ $selectedBill->number }} · {{ $selectedBill->connection->meter_number }}</flux:text></flux:card>
    @endif
</div>
<form method="GET" class="flex flex-wrap items-end gap-3">@if ($selectedBill)<input type="hidden" name="bill" value="{{ $selectedBill->id }}">@endif<flux:input placeholder="Search by consumer name or account number" name="search" label="Search consumer name or account" :value="request('search')" /><flux:input name="period" type="month" label="Payment month" :value="request('period')" /><flux:button type="submit">Search</flux:button></form>
        @can('permission', 'payments.view')
            <section id="payments" class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <flux:heading level="2" size="lg">{{ __('Payments') }}</flux:heading>
                    @can('permission', 'payments.manage')
                        <flux:modal.trigger name="create-payment"><flux:button variant="primary" icon="plus">{{ __('Record payment') }}</flux:button></flux:modal.trigger>
                    @endcan
                </div>
                @if ($selectedBill)
                    <flux:card class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div><flux:text>{{ __('Bill') }}</flux:text><a href="{{ route('bills.print', $selectedBill) }}" data-document-preview class="text-brand-primary focus-visible:outline-2 focus-visible:outline-offset-2 dark:text-brand-aqua">{{ $selectedBill->number }}</a></div>
                        <div><flux:text>{{ __('Consumer') }}</flux:text><a href="{{ route('provider.consumers.index', ['search' => $selectedBill->consumer->account_number]) }}" class="text-brand-primary focus-visible:outline-2 focus-visible:outline-offset-2 dark:text-brand-aqua">{{ $selectedBill->consumer->name }}</a></div>
                        <div><flux:text>{{ __('Meter and month') }}</flux:text><flux:text>{{ $selectedBill->connection->meter_number }} · {{ $selectedBill->period_month->format('M Y') }}</flux:text></div>
                        <div><flux:text>{{ __('Usage') }}</flux:text><flux:text class="tabular-nums">{{ $selectedBill->consumption }} m³</flux:text></div>
                        <div><flux:text>{{ __('Total') }}</flux:text><flux:text class="tabular-nums">₱{{ number_format((float) $selectedBill->total, 2) }}</flux:text></div>
                        <div><flux:text>{{ __('Remaining') }}</flux:text><flux:text class="tabular-nums">₱{{ number_format((float) $selectedBill->balance(), 2) }}</flux:text></div>
                        <div><flux:text>{{ __('Payment status') }}</flux:text><flux:text>{{ $selectedBill->paymentStatus() }}</flux:text></div>
                    </flux:card>
                @endif
                <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                    <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
                        <thead class="bg-brand-surface text-brand-ink dark:bg-zinc-800 dark:text-white"><tr><th scope="col" class="px-4 py-3">{{ __('Reference') }}</th><th scope="col" class="px-4 py-3">{{ __('Consumer') }}</th><th scope="col" class="px-4 py-3">{{ __('Bill') }}</th><th scope="col" class="px-4 py-3">{{ __('Meter') }}</th><th scope="col" class="px-4 py-3 text-right">{{ __('Amount') }}</th><th scope="col" class="px-4 py-3">{{ __('Status') }}</th><th scope="col" class="px-4 py-3">{{ __('Action') }}</th></tr></thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse ($payments as $payment)
                                @php($documentUrl = $payment->status === 'verified' ? route('payments.print', $payment) : ($payment->proof_path ? route('payments.proof', $payment) : null))
                                @php($isReceipt = $payment->status === 'verified')
                                @php($canVerify = auth()->user()->can('permission', 'payments.verify'))
                                <tr @if ($documentUrl) x-data x-on:click="if (! $event.target.closest('a, button, form')) $refs.document.click()" x-on:keydown.enter.prevent="$refs.document.click()" class="cursor-pointer" role="link" tabindex="0" @endif>
                                    <td class="px-4 py-3">@if ($isReceipt)<a x-ref="document" href="{{ $documentUrl }}" data-document-preview>{{ $payment->reference }}</a>@elseif ($documentUrl)<a x-ref="document" href="{{ $documentUrl }}">{{ $payment->reference }}</a>@else{{ $payment->reference }}@endif</td>
                                    <td class="px-4 py-3">{{ $payment->consumer->name }}</td>
                                    <td class="px-4 py-3">{{ $payment->bill->number }}</td>
                                    <td class="px-4 py-3">{{ $payment->bill->connection->meter_number }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">₱{{ number_format((float) $payment->amount, 2) }}</td>
                                    <td class="px-4 py-3">@if ($payment->status === 'verified')<flux:badge color="yellow">{{ __('Verified') }}</flux:badge>@elseif ($payment->status === 'rejected')<flux:badge color="red">{{ __('Rejected') }}</flux:badge>@else<flux:badge color="zinc">{{ ucfirst($payment->status) }}</flux:badge>@endif</td>
                                    <td class="px-4 py-3">
                                        @if ($documentUrl || ($payment->status === 'pending' && $canVerify))
                                            <flux:dropdown position="bottom" align="end"><flux:button class="size-11" icon="ellipsis-vertical" variant="ghost" aria-label="{{ __('Payment actions') }}" /><flux:menu>@if ($isReceipt)<flux:menu.item :href="$documentUrl" data-document-preview>{{ __('View receipt') }}</flux:menu.item>@elseif ($documentUrl)<flux:menu.item :href="$documentUrl">{{ __('View proof') }}</flux:menu.item>@endif @if ($payment->status === 'pending' && $canVerify)<form method="POST" action="{{ route('provider.payments.verify', $payment) }}">@csrf<flux:menu.item as="button" type="submit">{{ __('Verify') }}</flux:menu.item></form><flux:menu.item :href="request()->fullUrlWithQuery(['reject_payment' => $payment->id])">{{ __('Reject') }}</flux:menu.item>@endif</flux:menu></flux:dropdown>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-4 py-8 text-center">{{ __('No payments recorded yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $payments->links() }}
                @can('permission', 'payments.manage')
                    <x-form-modal name="create-payment" :title="__('Record payment')" :description="__('Payments remain pending until verified.')" :action="route('provider.payments.store')" :submit-label="__('Record payment')" :upload="true">
                        <flux:text>Showing up to 50 unpaid bills matching the page search. Narrow the search to find an older bill. Select a bill, then use its bill page to review the charges.</flux:text>
                        <div class="grid gap-4 sm:grid-cols-2" x-data="{ selectedBill: @js((string) old('bill_id', '')), billUrls: @js($billChoices->mapWithKeys(fn ($bill) => [$bill->id => route('bills.print', $bill)])) }">
                            <flux:select x-model="selectedBill" name="bill_id" :label="__('Bill')" required>
                                <option value="">{{ __('Choose bill') }}</option>
                                @foreach ($billChoices as $bill)
                                    <option value="{{ $bill->id }}" @selected(old('_form') === 'create-payment' && old('bill_id') == $bill->id)>{{ $bill->number }} · {{ $bill->consumer->name }} · ₱{{ bcsub($bill->total, (string) ($bill->allocations_sum_amount ?? '0'), 2) }} remaining</option>
                                @endforeach
                            </flux:select>
                            <a data-document-preview x-cloak x-show="billUrls[selectedBill]" x-bind:href="billUrls[selectedBill]" class="self-end py-2">View selected bill</a>
                            <flux:input placeholder="Enter the receipt or transaction reference" name="reference" :value="old('_form') === 'create-payment' ? old('reference') : null" :label="__('Reference')" required />
                            <flux:input placeholder="e.g. 250.00" name="amount" type="number" step="0.01" min="0.01" :value="old('_form') === 'create-payment' ? old('amount') : null" :label="__('Amount (PHP)')" required />
                            <flux:select name="method" :label="__('Method')">
                                @foreach (['cash', 'bank_transfer', 'e_wallet', 'other'] as $method)
                                    <option value="{{ $method }}" @selected(old('_form') === 'create-payment' && old('method') === $method)>{{ ucfirst(str_replace('_', ' ', $method)) }}</option>
                                @endforeach
                            </flux:select>
                            <flux:input placeholder="Select the payment date" name="paid_at" type="date" :value="old('_form') === 'create-payment' ? old('paid_at') : null" :label="__('Payment date')" required />
                            <flux:input name="proof" type="file" accept=".pdf,.jpg,.jpeg,.png" description="PDF, JPG or PNG, up to 5 MB." :label="__('Proof (optional)')" />
                        </div>
                    </x-form-modal>
                @endcan
                @can('permission', 'payments.verify')
                    @foreach ($selectedPayment && $selectedPayment->status === 'pending' ? [$selectedPayment] : [] as $payment)
                        <x-form-modal :open="true" :name="'reject-payment-'.$payment->id" :title="__('Reject payment')" :description="$payment->reference" :action="route('provider.payments.reject', $payment)" :submit-label="__('Reject payment')" variant="danger">
                            <flux:textarea placeholder="Explain why this payment is being rejected" name="remarks" :label="__('Reason for rejection')" required>{{ old('_form') === 'reject-payment-'.$payment->id ? old('remarks') : '' }}</flux:textarea>
                        </x-form-modal>
                    @endforeach
                @endcan
            </section>
        @endcan


@include("provider.adjustments")
</div>
</x-layouts::app>
