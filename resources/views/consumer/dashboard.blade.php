<x-layouts::app title="Client Dashboard">
<div class="client-page w-full min-w-0 space-y-6">
<x-page-header title="Dashboard" :description="$consumer->name.' · '.$consumer->account_number" />
@can('billing.view')
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <flux:card><flux:heading level="2">Current monthly bill</flux:heading><flux:text>{{ $currentBills->isEmpty() ? 'No bill generated for this month.' : '₱'.$currentTotal.' · '.$currentBills->count().' bill(s)' }}</flux:text></flux:card>
    <flux:card><flux:heading level="2">Outstanding balance</flux:heading><flux:text>₱{{ bcadd($outstanding, '0', 2) }}</flux:text></flux:card>
    <flux:card><flux:heading level="2">Payment status</flux:heading><flux:text>{{ $paymentStatus }}</flux:text></flux:card>
    <flux:card><flux:heading level="2">Upcoming due date</flux:heading><flux:text>{{ $upcomingBill ? $upcomingBill->due_at->format('M d, Y') : 'No upcoming payment due.' }}</flux:text></flux:card>
</div>
@if ($overdueBill)
    <x-feedback-toast variant="warning" :duration="0" :text="__('Overdue: :number was due :date.', ['number' => $overdueBill->number, 'date' => $overdueBill->due_at->format('M d, Y')])" :link="['label' => __('Review your bills'), 'href' => route('portal.section', 'bills')]" />
@endif
@endcan
<flux:card><flux:heading level="2">Latest water consumption</flux:heading><flux:text>{{ $latestReading ? bcsub($latestReading->current_reading, $latestReading->previous_reading, 3).' m³ · '.$latestReading->period_month->format('M Y').' · Meter '.$latestReading->connection->meter_number : 'No readings yet.' }}</flux:text><flux:link :href="route('portal.section', 'connection')">View connection and reading history</flux:link></flux:card>
<div class="grid gap-6 xl:grid-cols-2">
    @can('billing.view')
        <x-dashboard-chart :title="__('Monthly water bills')" :description="__('Total billed across your connections by billing month, excluding void bills.')" :data="$financialTrend" :series="['billed' => __('Billed')]" />
    @endcan
    <x-dashboard-chart :title="__('Water consumption')" :description="__('Your metered usage across all connections by reading month.')" :data="$consumptionTrend" :series="['consumption' => __('Consumption')]" unit="m³" />
</div>
@can('billing.view')
<section class="space-y-3"><flux:heading level="2" size="lg">This month’s bills</flux:heading>
@forelse ($currentBills as $bill)
    <flux:card><flux:link :href="route('bills.print', $bill)" data-document-preview>{{ $bill->number }}</flux:link><flux:text>{{ $bill->consumption }} m³ · {{ $bill->paymentStatus() }} · ₱{{ $bill->balance() }} remaining · Due {{ $bill->due_at->format('M d, Y') }}</flux:text></flux:card>
@empty <flux:text>Your provider has not generated a bill for the current month.</flux:text> @endforelse
</section>
@endcan
<div class="flex flex-wrap gap-3">
    @can('payments.view')<flux:button :href="route('portal.section', 'payments')">View payments</flux:button>@endcan
    @can('notifications.view')<flux:button :href="route('portal.section', 'notifications')">View notifications</flux:button>@endcan
    <flux:button :href="route('portal.section', 'support')">Get help</flux:button>
</div>
</div>
</x-layouts::app>
