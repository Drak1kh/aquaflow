<x-layouts::app :title="$bill->number">
    <div class="w-full min-w-0 space-y-7"><x-page-header :title="$bill->number" :description="$bill->waterProvider->name.' · '.$bill->consumer->name.' · '.$bill->period_month->format('F Y')" :back-href="route('admin.billing.index')" :back-label="__('Billing oversight')"><x-slot:actions><flux:button :href="route('bills.print', $bill)" data-document-preview icon="printer">{{ __('View / print bill') }}</flux:button></x-slot:actions></x-page-header><dl class="grid gap-4 rounded-xl border border-zinc-200 bg-white p-6 sm:grid-cols-2 dark:border-zinc-700 dark:bg-zinc-900">
@foreach ([[__('Previous reading'), $bill->previous_reading], [__('Current reading'), $bill->current_reading], [__('Consumption (m³)'), $bill->consumption], [__('Base charge (PHP)'), $bill->base_charge], [__('Rate per m³ (PHP)'), $bill->unit_rate], [__('Consumption charge (PHP)'), $bill->consumption_charge], [__('Late fee (PHP)'), $bill->late_fee], [__('Total (PHP)'), $bill->total], [__('Balance (PHP)'), $bill->balance()], [__('Due date'), $bill->due_at->format(app(\App\Actions\PlatformSettings::class)->get('date_format'))]] as [$label, $value])<div><dt class="text-sm text-zinc-500">{{ $label }}</dt><dd class="mt-1 font-medium tabular-nums">{{ $value }}</dd></div>
@endforeach</dl><flux:badge color="blue">{{ $bill->paymentStatus() }}</flux:badge>
        <section class="space-y-3"><flux:heading level="2">Approved adjustments</flux:heading>
        @forelse ($bill->adjustments as $adjustment)
            <flux:text>{{ ucfirst($adjustment->type) }} ₱{{ $adjustment->amount }} · {{ $adjustment->reason }} · {{ $adjustment->created_at->format('M d, Y') }}</flux:text>
        @empty <flux:text>No adjustments recorded.</flux:text> @endforelse
        </section>
        <section><flux:heading level="2" size="lg">{{ __('Billing activity') }}</flux:heading><div class="mt-3 divide-y divide-zinc-200 rounded-xl border border-zinc-200 bg-white dark:divide-zinc-700 dark:border-zinc-700 dark:bg-zinc-900">
@forelse ($activity as $event)<div class="flex justify-between gap-3 px-5 py-3 text-sm"><span>{{ str_replace('.', ' · ', $event->action) }}</span><time class="text-zinc-500">{{ $event->created_at->format(app(\App\Actions\PlatformSettings::class)->get('date_format').' g:i A') }}</time></div>
@empty<div class="px-5 py-8 text-sm">{{ __('No billing changes have been logged.') }}</div>
@endforelse</div></section></div>
</x-layouts::app>
