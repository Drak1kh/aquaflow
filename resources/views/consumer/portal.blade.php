<x-layouts::app :title="__('My water account')">
    <div class="mx-auto flex max-w-5xl flex-col gap-8">
        <x-page-header :title="__('My water account')" :description="$consumer ? $consumer->name.' · '.$consumer->account_number : null">
            <x-slot:actions>
                @if ($consumer)
                    @can('permission', 'tickets.create')
                        <flux:modal.trigger name="create-support-ticket">
                            <flux:button variant="primary" icon="plus">{{ __('Ask for help') }}</flux:button>
                        </flux:modal.trigger>
                    @endcan
                @endif
            </x-slot:actions>
        </x-page-header>

        @if (! $consumer)
            <flux:callout variant="warning">{{ __('Your portal account is not linked to a consumer record. Contact your water provider.') }}</flux:callout>
        @else
            @can('permission', 'billing.view')
                <section class="space-y-3">
                    <flux:heading size="lg">{{ __('Bills') }}</flux:heading>
                    <div class="divide-y divide-zinc-200 rounded-xl border border-zinc-200 bg-white dark:divide-zinc-700 dark:border-zinc-700 dark:bg-zinc-900">
                        @forelse ($bills as $bill)
                            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4"><div class="font-medium">{{ $bill->number }} <span class="text-sm font-normal text-zinc-500">· {{ $bill->period_month->format('M Y') }}</span></div><div class="tabular-nums">₱{{ number_format((float) $bill->balance(), 2) }} <span class="text-sm text-zinc-500">{{ __('remaining') }}</span></div></div>
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

            @can('permission', 'tickets.view')
                <section class="space-y-3">
                    <flux:heading size="lg">{{ __('Support tickets') }}</flux:heading>
                    <div class="divide-y divide-zinc-200 rounded-xl border border-zinc-200 bg-white dark:divide-zinc-700 dark:border-zinc-700 dark:bg-zinc-900">
                        @forelse ($tickets as $ticket)
                            <a href="{{ route('portal.tickets.show', $ticket) }}" class="flex justify-between gap-3 px-5 py-4 hover:bg-[#F7FCFE] dark:hover:bg-zinc-800"><span>{{ $ticket->subject }}</span><span class="text-sm text-zinc-500">{{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</span></a>
                        @empty
                            <div class="px-5 py-7 text-sm">{{ __('No support tickets yet.') }}</div>
                        @endforelse
                    </div>
                </section>
            @endcan

            @can('permission', 'tickets.create')
                <x-form-modal name="create-support-ticket" :title="__('Ask for help')" :action="route('portal.tickets.store')" :submit-label="__('Submit ticket')">
                    <div class="space-y-4">
                        <flux:select name="type" :label="__('Concern type')">
                            @foreach (['billing', 'payment', 'account', 'service', 'provider', 'other'] as $type)
                                <option value="{{ $type }}" @selected(old('_form') === 'create-support-ticket' && old('type') === $type)>{{ ucfirst($type) }}</option>
                            @endforeach
                        </flux:select>
                        <flux:input name="subject" :value="old('_form') === 'create-support-ticket' ? old('subject') : null" :label="__('Subject')" required />
                        <flux:textarea name="description" :label="__('Describe your concern')" required>{{ old('_form') === 'create-support-ticket' ? old('description') : '' }}</flux:textarea>
                    </div>
                </x-form-modal>
            @endcan
        @endif
    </div>
</x-layouts::app>
