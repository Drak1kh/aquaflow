<x-layouts::app :title="__('Billing oversight')">
    <div class="flex w-full min-w-0 flex-col gap-7">
        <x-page-header :title="__('Billing oversight')" :description="__('Inspect bills across providers and follow reported billing concerns.')" />
        <form method="GET" class="flex flex-wrap items-end gap-3"><flux:select name="provider" :label="__('Provider')"><option value="">{{ __('All providers') }}</option>
@foreach ($providers as $provider)<option value="{{ $provider->id }}" @selected(request('provider') == $provider->id)>{{ $provider->name }}</option>
@endforeach</flux:select><x-month-input name="month" :value="request('month')" :label="__('Billing month')" /><flux:select name="status" :label="__('Status')"><option value="">{{ __('All statuses') }}</option>
@foreach (['unpaid', 'partially_paid', 'paid', 'overdue', 'void'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
@endforeach</flux:select><flux:button type="submit">{{ __('Apply filters') }}</flux:button></form>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900"><table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700"><thead class="bg-brand-surface text-brand-ink dark:bg-zinc-800 dark:text-white"><tr><th scope="col" class="px-5 py-3">{{ __('Bill') }}</th><th scope="col" class="px-5 py-3">{{ __('Provider / consumer') }}</th><th scope="col" class="px-5 py-3">{{ __('Month') }}</th><th scope="col" class="px-5 py-3 text-right">{{ __('Total') }}</th><th scope="col" class="px-5 py-3 text-right">{{ __('Balance') }}</th><th scope="col" class="px-5 py-3">{{ __('Status') }}</th></tr></thead><tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
@forelse ($bills as $bill)<tr><td class="px-5 py-4"><flux:link :href="route('admin.billing.show', $bill)">{{ $bill->number }}</flux:link></td><td class="px-5 py-4">{{ $bill->waterProvider->name }}<div class="text-zinc-500">{{ $bill->consumer->name }}</div></td><td class="px-5 py-4">{{ $bill->period_month->format('M Y') }}</td><td class="px-5 py-4 text-right tabular-nums">₱{{ number_format((float) $bill->total, 2) }}</td><td class="px-5 py-4 text-right tabular-nums">₱{{ number_format((float) $bill->balance(), 2) }}</td><td class="px-5 py-4"><flux:badge color="blue">{{ $bill->paymentStatus() }}</flux:badge></td></tr>
@empty<tr><td colspan="6" class="px-5 py-10 text-center text-zinc-600 dark:text-zinc-300">{{ __('No bills match these filters. Provider bills appear here after generation.') }}</td></tr>
@endforelse</tbody></table></div>
        {{ $bills->links() }}
        <section><div class="flex items-center justify-between"><flux:heading level="2" size="lg">{{ __('Billing concerns') }}</flux:heading><flux:link :href="route('admin.tickets.index', ['type' => 'billing'])">{{ __('All disputes') }}</flux:link></div><div class="mt-3 divide-y divide-zinc-200 rounded-xl border border-zinc-200 bg-white dark:divide-zinc-700 dark:border-zinc-700 dark:bg-zinc-900">
@forelse ($issues as $ticket)<a class="block px-5 py-3 hover:bg-brand-surface dark:hover:bg-zinc-800" href="{{ route('admin.tickets.show', $ticket) }}">{{ $ticket->subject }} <span class="text-sm text-zinc-500">· {{ $ticket->waterProvider->name }}</span></a>
@empty<div class="px-5 py-6 text-sm text-zinc-600 dark:text-zinc-300">{{ __('No escalated billing concerns.') }}</div>
@endforelse</div></section>
    </div>
</x-layouts::app>
