<section class="space-y-4">
    <flux:heading level="2" size="lg">Approved bill adjustments</flux:heading>
    @foreach ($bills as $bill)
        @foreach ($bill->adjustments as $adjustment)
            <flux:text>{{ $bill->number }} · {{ ucfirst($adjustment->type) }} ₱{{ $adjustment->amount }} · {{ $adjustment->reason }} · Approved {{ $adjustment->created_at->format('M d, Y') }}</flux:text>
        @endforeach
    @endforeach
    {{ $bills->links() }}
    @can('access-super-admin')
        @foreach ($bills->where('status', '!=', 'void') as $bill)
        <details @if(old('_form') === 'adjust-'.$bill->id) open @endif class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700"><summary>Adjust {{ $bill->number }} · {{ $bill->consumer->name }} · ₱{{ $bill->balance() }} remaining</summary>
        <form method="POST" action="{{ route('provider.adjustments.store', $bill) }}" class="mt-4 space-y-4">
            @csrf
            <input type="hidden" name="_form" value="adjust-{{ $bill->id }}">
            <flux:select name="type" label="Adjustment type"><option value="credit" @selected(old('_form') !== 'adjust-'.$bill->id || old('type') === 'credit')>Credit (reduce charge)</option><option value="debit" @selected(old('_form') === 'adjust-'.$bill->id && old('type') === 'debit')>Debit (increase charge)</option></flux:select>
            <flux:input placeholder="e.g. 250.00" :value="old('_form') === 'adjust-'.$bill->id ? old('amount') : null" name="amount" label="Amount (PHP)" type="number" step="0.01" min="0.01" required />
            <flux:textarea placeholder="Explain the approved billing correction" name="reason" label="Approval reason" required>{{ old('_form') === 'adjust-'.$bill->id ? old('reason') : '' }}</flux:textarea>
            <flux:button type="submit" variant="primary">Approve and apply adjustment</flux:button>
        </form>
        </details>
        @endforeach
    @endcan
</section>
