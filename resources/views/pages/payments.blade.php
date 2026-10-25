<x-layouts::app :title="__('Payments')">
    <main class="flex w-full min-w-0 flex-col gap-8 py-8 text-[#062E68] dark:text-white">
        <header><flux:heading size="xl">Payments · {{ $provider->name }}</flux:heading><flux:text>Record, verify, and review collections</flux:text></header>
        @include('partials.aquaflow-alerts')

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">@foreach ($aging as $label => $amount)<flux:card><flux:text>{{ $label }}</flux:text><flux:heading>{{ \App\DecimalAmount::format($amount) }}</flux:heading></flux:card>@endforeach</div>

        @if (auth()->user()->canInProvider('payments.record', $provider->id))
            <flux:card class="space-y-4"><flux:heading>Record payment</flux:heading>
                <form method="POST" action="{{ route('provider.payments.store') }}" enctype="multipart/form-data" class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">@csrf
                    <flux:select name="bill_id" label="Bill" required>
                        @foreach ($bills as $bill)
                            <flux:select.option value="{{ $bill->id }}">{{ $bill->bill_number }} · {{ $bill->consumer->name }} · Balance {{ \App\DecimalAmount::format($bill->balanceCents()) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input name="amount" label="Amount" type="number" step="0.01" min="0.01" required />
                    <flux:select name="method" label="Method" required><flux:select.option value="cash">Cash</flux:select.option><flux:select.option value="bank_transfer">Bank transfer</flux:select.option><flux:select.option value="e_wallet">E-wallet</flux:select.option><flux:select.option value="other">Other</flux:select.option></flux:select>
                    <flux:input name="proof" label="Proof (optional)" type="file" accept=".jpg,.jpeg,.png,.pdf" />
                    <flux:input name="remarks" label="Remarks" />
                    <div class="flex items-end"><flux:button type="submit" variant="primary">Record payment</flux:button></div>
                </form>
            </flux:card>
        @endif

        <flux:card class="space-y-4"><flux:heading>Payment history</flux:heading>
            <div class="overflow-x-auto"><flux:table>
                <flux:table.columns><flux:table.column>Reference</flux:table.column><flux:table.column>Consumer</flux:table.column><flux:table.column>Amount</flux:table.column><flux:table.column>Method</flux:table.column><flux:table.column>Status</flux:table.column><flux:table.column>Actions</flux:table.column></flux:table.columns>
                <flux:table.rows>
                    @foreach ($payments as $payment)
                        <flux:table.row>
                            <flux:table.cell>{{ $payment->reference_number }}</flux:table.cell>
                            <flux:table.cell>{{ $payment->consumer->name }}</flux:table.cell>
                            <flux:table.cell>{{ $payment->amount }}</flux:table.cell>
                            <flux:table.cell>{{ str_replace('_', ' ', $payment->method) }}</flux:table.cell>
                            <flux:table.cell><flux:badge>{{ $payment->status }}</flux:badge></flux:table.cell>
                            <flux:table.cell><div class="flex flex-wrap gap-2">
                                @if ($payment->proof_path)<a class="text-[#079EE9] underline" href="{{ route('provider.payments.proof', $payment) }}">Proof</a>@endif
                                @if ($payment->status === 'verified')<a class="text-[#079EE9] underline" href="{{ route('provider.payments.receipt', $payment) }}">Receipt</a>@endif
                                @if (auth()->user()->canInProvider('payments.verify', $provider->id))
                                    @if ($payment->status === 'pending')
                                        <form method="POST" action="{{ route('provider.payments.verify', $payment) }}">@csrf<flux:button type="submit" size="sm" variant="primary">Verify</flux:button></form>
                                        <form method="POST" action="{{ route('provider.payments.reject', $payment) }}" class="flex gap-2">@csrf<input name="remarks" aria-label="Rejection reason" placeholder="Reason" required class="w-28 rounded border px-2 dark:bg-zinc-800"><flux:button type="submit" size="sm">Reject</flux:button></form>
                                    @elseif ($payment->status === 'verified')
                                        <form method="POST" action="{{ route('provider.payments.reverse', $payment) }}" class="flex gap-2">@csrf<input name="remarks" aria-label="Reversal reason" placeholder="Reason" required class="w-28 rounded border px-2 dark:bg-zinc-800"><flux:button type="submit" size="sm">Reverse</flux:button></form>
                                    @endif
                                @endif
                            </div></flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table></div>
            {{ $payments->links() }}
        </flux:card>
    </main>
</x-layouts::app>
