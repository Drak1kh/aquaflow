<x-layouts::app :title="__('My water account')">
    <div class="mx-auto flex max-w-5xl flex-col gap-8">
        <x-page-header :title="__('My water account')" :description="$consumer ? $consumer->name.' · '.$consumer->account_number : null">
        </x-page-header>

        @if (! $consumer)
            <flux:callout variant="warning">{{ __('Your portal account is not linked to a consumer record. Contact your water provider.') }}</flux:callout>
        @else
            <flux:heading>Outstanding balance: ₱{{ $bills->where('status', '!=', 'void')->reduce(fn ($sum, $bill) => bcadd($sum, $bill->balance(), 2), '0.00') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:card><flux:heading>Current monthly bill</flux:heading><flux:text>{{ $currentBill ? '₱'.$currentBill->total.' · '.$currentBill->paymentStatus() : 'No bill generated for this month.' }}</flux:text></flux:card>
                <flux:card><flux:heading>Latest consumption</flux:heading><flux:text>{{ $latestReading ? bcsub($latestReading->current_reading, $latestReading->previous_reading, 3).' m³ · '.$latestReading->period_month->format('M Y') : 'No readings yet.' }}</flux:text></flux:card>
                <flux:card><flux:heading>Next unpaid bill due</flux:heading><flux:text>{{ $nextBill ? $nextBill->due_at->format('M d, Y').' · '.$nextBill->paymentStatus() : 'No outstanding bills.' }}</flux:text></flux:card>
            </div>
            @can('permission', 'billing.view')
                <section class="space-y-3">
                    <flux:heading size="lg">{{ __('Bills') }}</flux:heading>
                    <div class="divide-y divide-zinc-200 rounded-xl border border-zinc-200 bg-white dark:divide-zinc-700 dark:border-zinc-700 dark:bg-zinc-900">
                        @forelse ($bills as $bill)
                            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4"><div class="font-medium"><a href="{{ route('bills.print', $bill) }}">{{ $bill->number }} · {{ $bill->paymentStatus() }}</a> <span class="text-sm font-normal text-zinc-500">· {{ $bill->period_month->format('M Y') }}</span></div><div class="tabular-nums">₱{{ number_format((float) $bill->balance(), 2) }} <span class="text-sm text-zinc-500">{{ __('remaining') }}</span></div></div>
                        @empty
                            <div class="px-5 py-7 text-sm">{{ __('Your provider has not generated any bills yet.') }}</div>
                        @endforelse
                    </div>
                </section>
            @endcan

            @can('permission', 'payments.view')
                <section class="space-y-3">
                    <flux:heading size="lg">{{ __('Payments') }}</flux:heading>
                    <div class="divide-y divide-zinc-200 rounded-xl border border-zinc-200 bg-white dark:divide-zinc-700 dark:border-zinc-700 dark:bg-zinc-900">
                        @forelse ($payments as $payment)
                            <div class="flex flex-wrap justify-between gap-3 px-5 py-4"><div>{{ $payment->reference }} <span class="text-sm text-zinc-500">· {{ $payment->paid_at->format(app(\App\Actions\PlatformSettings::class)->get('date_format')) }}</span></div><div class="tabular-nums">₱{{ number_format((float) $payment->amount, 2) }} · {{ ucfirst($payment->status) }}</div></div>
                        @empty
                            <div class="px-5 py-7 text-sm">{{ __('No payments are recorded for your account.') }}</div>
                        @endforelse
                    </div>
                </section>
            @endcan

        @endif
    </div>
</x-layouts::app>
