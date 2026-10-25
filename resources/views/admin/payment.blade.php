<x-layouts::app :title="$payment->reference">
    <div class="w-full min-w-0 space-y-7">
        <x-page-header :title="$payment->reference" :description="$payment->waterProvider->name.' · '.$payment->consumer->name" :back-href="route('admin.payments.index')" :back-label="__('Payment oversight')" />
        <dl class="grid gap-4 rounded-xl border border-zinc-200 bg-white p-6 sm:grid-cols-2 dark:border-zinc-700 dark:bg-zinc-900">
            @foreach ([[__('Bill'), $payment->bill->number], [__('Amount (PHP)'), $payment->amount], [__('Method'), ucfirst(str_replace('_', ' ', $payment->method))], [__('Payment date'), $payment->paid_at->format(app(\App\Actions\PlatformSettings::class)->get('date_format'))], [__('Status'), ucfirst($payment->status)], [__('Remarks'), $payment->remarks ?? '—']] as [$label, $value])
                <div><dt class="text-sm text-zinc-500">{{ $label }}</dt><dd class="mt-1 font-medium">{{ $value }}</dd></div>
            @endforeach
        </dl>
        @if ($payment->proof_path)
            <flux:button :href="route('admin.payments.proof', $payment)">{{ __('Download proof of payment') }}</flux:button>
        @endif
        <section><flux:heading size="lg">{{ __('Verification and audit history') }}</flux:heading>
            <div class="mt-3 divide-y divide-zinc-200 rounded-xl border border-zinc-200 bg-white dark:divide-zinc-700 dark:border-zinc-700 dark:bg-zinc-900">
                @forelse ($activity as $event)
                    <div class="flex justify-between gap-3 px-5 py-3 text-sm"><span>{{ str_replace('.', ' · ', $event->action) }}</span><time class="text-zinc-500">{{ $event->created_at->format(app(\App\Actions\PlatformSettings::class)->get('date_format').' g:i A') }}</time></div>
                @empty
                    <div class="px-5 py-8 text-sm">{{ __('No payment changes have been logged.') }}</div>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts::app>
