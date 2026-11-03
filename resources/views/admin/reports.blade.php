<x-layouts::app :title="__('Reports and analytics')">
    <div class="flex w-full min-w-0 flex-col gap-7"><x-page-header :title="__('Reports and analytics')" :description="__('Platform records, filtered and ready to export.')" />
        <form method="GET" class="grid gap-3 rounded-xl border border-zinc-200 bg-white p-5 sm:grid-cols-2 lg:grid-cols-5 dark:border-zinc-700 dark:bg-zinc-900"><flux:select name="type" :label="__('Report')">
@foreach ($types as $type)<option value="{{ $type }}" @selected($filters['type'] === $type)>{{ ucfirst($type) }}</option>
@endforeach</flux:select><flux:select name="provider" :label="__('Provider')"><option value="">{{ __('All providers') }}</option>
@foreach ($providers as $provider)<option value="{{ $provider->id }}" @selected($filters['provider'] === $provider->id)>{{ $provider->name }}</option>
@endforeach</flux:select><flux:input name="from" type="date" :value="$filters['from']" :label="__('From')" /><flux:input name="to" type="date" :value="$filters['to']" :label="__('To')" /><div class="flex items-end"><flux:button type="submit" variant="primary">{{ __('Generate') }}</flux:button></div></form>
        <div class="flex flex-wrap gap-3"><flux:button :href="route('admin.reports.csv', request()->query())" icon="arrow-down-tray">{{ __('Download CSV') }}</flux:button><flux:button :href="route('admin.reports.print', request()->query())" data-document-preview icon="printer">{{ __('Print / save PDF') }}</flux:button></div>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900"><table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
<thead class="bg-brand-surface text-brand-ink dark:bg-zinc-800 dark:text-white"><tr>
@foreach ($headers as $heading)<th scope="col" class="px-5 py-3 whitespace-nowrap">{{ $heading }}</th>
@endforeach</tr></thead>
<tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
@forelse ($rows as $row)<tr>
@foreach ($row as $value)<td class="px-5 py-3 whitespace-nowrap tabular-nums">{{ $value }}</td>
@endforeach</tr>
@empty<tr><td class="px-5 py-10 text-center text-zinc-600 dark:text-zinc-300">{{ __('No records match this report and filter.') }}</td></tr>
@endforelse</tbody></table></div>
    </div>
</x-layouts::app>
