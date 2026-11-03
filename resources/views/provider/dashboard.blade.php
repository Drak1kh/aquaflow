<x-layouts::app title="Dashboard">
<div class="provider-page w-full min-w-0 space-y-6">
    <x-page-header title="Dashboard" :description="$provider->name" />
    @include('provider.metrics')
    <flux:card><flux:heading level="2">Monthly consumption</flux:heading><flux:text>{{ $monthlyConsumption }} m³</flux:text></flux:card>
    <flux:card><flux:heading level="2">Unpaid bills</flux:heading><flux:text>{{ $unpaidCount }}</flux:text></flux:card>
    <flux:heading level="2" size="lg">Recent billing activity</flux:heading>
    @forelse ($bills->take(5) as $bill)
        <flux:text><a href="{{ route('bills.print', $bill) }}">{{ $bill->number }}</a> · {{ $bill->consumer->name }} · ₱{{ $bill->total }} · {{ $bill->paymentStatus() }}</flux:text>
    @empty <flux:text>No bills generated yet.</flux:text> @endforelse
    <flux:heading level="2" size="lg">Recent payments</flux:heading>
    @forelse ($payments->take(5) as $payment)
        <flux:text>{{ $payment->reference }} · ₱{{ $payment->amount }} · {{ ucfirst($payment->status) }}</flux:text>
    @empty <flux:text>No payments recorded yet.</flux:text> @endforelse
</div>
</x-layouts::app>
