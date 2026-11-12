<x-layouts::app title="My Bills">
<div class="client-page w-full min-w-0 space-y-6">
<x-page-header title="My Bills" :description="$consumer->account_number" />
@forelse($bills as $bill)
<flux:card class="space-y-4">
    @php($status = $bill->paymentStatus())
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div><flux:heading level="2" size="lg">{{ $bill->number }}</flux:heading><flux:text class="mt-1 text-sm">{{ $bill->period_month->format('M Y') }}</flux:text></div>
        <flux:badge :color="match ($status) { 'Paid' => 'green', 'Partially Paid', 'Unpaid' => 'yellow', 'Overdue' => 'red', default => 'zinc' }">{{ $status }}</flux:badge>
    </div>
    <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">Consumption</dt><dd class="mt-1 font-medium tabular-nums">{{ $bill->consumption }} m³</dd></div>
        <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">Total</dt><dd class="mt-1 font-medium tabular-nums">₱{{ $bill->total }}</dd></div>
        <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">Due date</dt><dd class="mt-1 font-medium">{{ $bill->due_at->format('M d, Y') }}</dd></div>
        <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">Remaining balance</dt><dd class="mt-1 font-medium tabular-nums">₱{{ $bill->balance() }}</dd></div>
    </dl>
    <div class="flex flex-wrap gap-3"><flux:button :href="route('bills.print', $bill)" data-document-preview>View / print bill receipt</flux:button>
    @can('payments.view')@if($bill->status !== 'void' && bccomp($bill->balance(), '0', 2) > 0)<flux:button :href="route('portal.section', ['section' => 'payments', 'bill' => $bill->id])">Submit payment details</flux:button>@endif@endcan</div>
</flux:card>
@empty <flux:text>Your provider has not generated any bills yet.</flux:text> @endforelse
{{ $bills->links() }}
</div>
</x-layouts::app>
