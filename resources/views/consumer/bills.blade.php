<x-layouts::app title="My Bills">
<div class="client-page w-full min-w-0 space-y-6">
<x-page-header title="My Bills" :description="$consumer->account_number" />
@forelse($bills as $bill)
<article class="space-y-3 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
    <flux:heading level="2">{{ $bill->number }} · {{ $bill->period_month->format('M Y') }}</flux:heading>
    <flux:text>{{ $bill->consumption }} m³ · Total ₱{{ $bill->total }} · {{ $bill->paymentStatus() }} · Due {{ $bill->due_at->format('M d, Y') }} · ₱{{ $bill->balance() }} remaining</flux:text>
    <div class="flex flex-wrap gap-3"><flux:button :href="route('bills.print', $bill)">View / print bill</flux:button>
    @can('payments.view')@if($bill->status !== 'void' && bccomp($bill->balance(), '0', 2) > 0)<flux:button :href="route('portal.section', ['section' => 'payments', 'bill' => $bill->id])">Submit payment details</flux:button>@endif@endcan</div>
</article>
@empty <flux:text>Your provider has not generated any bills yet.</flux:text> @endforelse
{{ $bills->links() }}
</div>
</x-layouts::app>
