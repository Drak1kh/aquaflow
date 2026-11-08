<x-layouts::app title="Consumer Management">
<div class="provider-page w-full min-w-0 space-y-6">
@if ($previewedConsumer)
    @php($consumerIndexUrl = request()->fullUrlWithQuery(['preview_consumer' => null, 'edit_consumer' => null, 'portal_consumer' => null, 'period' => null]))
    @php($consumerDetailUrl = request()->fullUrlWithQuery(['period' => null]))
    <x-page-header :title="$previewedConsumer->name" :description="$previewedConsumer->account_number" :back-href="$consumerIndexUrl" :back-label="__('Back to consumers')" />
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="$consumerIndexUrl">{{ __('Consumer Management') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $previewedConsumer->name }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>
@else
    <x-page-header title="Consumer Management" />

<form method="GET" class="flex flex-col gap-3 sm:flex-row sm:items-end"><flux:input placeholder="Search by consumer name or account number" name="search" label="Search consumer name or account" :value="request('search')" /><flux:button type="submit">Search</flux:button></form>
        @can('permission', 'consumers.view')
            <section id="consumers" class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3"><flux:heading level="2" size="lg">{{ __('Consumers') }}</flux:heading><flux:badge color="blue">{{ $consumers->total() }}</flux:badge></div>
                    @can('permission', 'consumers.manage')
                        <flux:modal.trigger name="create-consumer"><flux:button variant="primary" icon="plus">{{ __('Register consumer') }}</flux:button></flux:modal.trigger>
                    @endcan
                </div>
                <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                    <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
                        <thead class="bg-brand-surface text-brand-ink dark:bg-zinc-800 dark:text-white"><tr><th scope="col" class="px-4 py-3">{{ __('Account') }}</th><th scope="col" class="px-4 py-3">{{ __('Consumer') }}</th><th scope="col" class="px-4 py-3">{{ __('Contact') }}</th><th scope="col" class="px-4 py-3">{{ __('Service address') }}</th><th scope="col" class="px-4 py-3">{{ __('Connection') }}</th><th scope="col" class="px-4 py-3">{{ __('Portal') }}</th><th scope="col" class="px-4 py-3">{{ __('Status') }}</th><th scope="col" class="px-4 py-3">{{ __('Actions') }}</th></tr></thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse ($consumers as $consumer)
                                <tr>
                                    <td class="px-4 py-3"><a href="{{ request()->fullUrlWithQuery(['preview_consumer' => $consumer->id, 'edit_consumer' => null, 'portal_consumer' => null]) }}" class="font-medium text-brand-primary no-underline dark:text-brand-aqua">{{ $consumer->account_number }}</a></td>
                                    <td class="px-4 py-3"><a href="{{ request()->fullUrlWithQuery(['preview_consumer' => $consumer->id, 'edit_consumer' => null, 'portal_consumer' => null]) }}" class="font-medium text-brand-primary no-underline dark:text-brand-aqua">{{ $consumer->name }}</a></td>
                                    <td class="px-4 py-3">{{ $consumer->phone }}<br>{{ $consumer->email }}</td><td class="px-4 py-3">{{ $consumer->address }}</td><td class="px-4 py-3">@forelse ($consumer->connections as $connection)<div>{{ $connection->number }} · {{ $connection->meter_number }}</div><div class="text-sm text-zinc-500 dark:text-zinc-400">{{ $connection->installed_at?->format('M d, Y') ?? __('Installation date not recorded') }}</div>@empty — @endforelse</td>
                                    <td class="px-4 py-3">{{ $consumer->portalUser ? __('Active') : __('Not activated') }}</td>
                                    <td class="px-4 py-3"><flux:badge :color="$consumer->is_active ? 'green' : 'zinc'">{{ $consumer->is_active ? __('Active') : __('Inactive') }}</flux:badge></td>
                                    <td class="px-4 py-3">
                                        <flux:dropdown position="bottom" align="end">
                                            <flux:button icon="ellipsis-vertical" variant="ghost" aria-label="{{ __('Consumer actions') }}" />
                                            <flux:menu>
                                                <flux:menu.item :href="request()->fullUrlWithQuery(['preview_consumer' => $consumer->id, 'edit_consumer' => null, 'portal_consumer' => null])">{{ __('View') }}</flux:menu.item>
                                                @can('permission', 'consumers.manage')
                                                <flux:menu.item :href="request()->fullUrlWithQuery(['edit_consumer' => $consumer->id, 'portal_consumer' => null])">{{ __('Edit') }}</flux:menu.item>
                                                @if (! $consumer->portalUser)
                                                    <flux:menu.item :href="request()->fullUrlWithQuery(['portal_consumer' => $consumer->id, 'edit_consumer' => null])">{{ __('Activate portal') }}</flux:menu.item>
                                                @endif
                                                <form method="POST" action="{{ route('provider.consumers.status', $consumer) }}">
                                                    @csrf @method('PATCH')
                                                    <flux:menu.item as="button" type="submit">{{ $consumer->is_active ? __('Deactivate') : __('Activate') }}</flux:menu.item>
                                                </form>
                                                @endcan
                                            </flux:menu>
                                        </flux:dropdown>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="px-4 py-8 text-center">{{ __('Register a consumer to begin.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $consumers->links() }}
                @can('permission', 'consumers.manage')
                    <x-form-modal name="create-consumer" :title="__('Register consumer')" :action="route('provider.consumers.store')" :submit-label="__('Register consumer')">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:input placeholder="Leave blank to generate automatically" name="account_number" :value="old('_form') === 'create-consumer' ? old('account_number') : null" :label="__('Account number (leave blank to generate)')" />
                            <flux:input placeholder="Enter the full name" name="name" :value="old('_form') === 'create-consumer' ? old('name') : null" :label="__('Full name')" required />
                            <flux:input placeholder="e.g. consumer@example.com" name="email" type="email" :value="old('_form') === 'create-consumer' ? old('email') : null" :label="__('Email')" />
                            <flux:input placeholder="e.g. 09123456789" name="phone" type="tel" inputmode="numeric" pattern="[0-9]*" :value="old('_form') === 'create-consumer' ? old('phone') : null" :label="__('Phone')" />
                            <div class="sm:col-span-2"><flux:input placeholder="House number, street, barangay, city" name="address" :value="old('_form') === 'create-consumer' ? old('address') : null" :label="__('Service address')" required /></div>
                            <flux:input placeholder="e.g. CON-000123" name="number" :value="old('_form') === 'create-consumer' ? old('number') : null" :label="__('Connection number')" required />
                            <flux:input placeholder="e.g. MTR-000123" name="meter_number" :value="old('_form') === 'create-consumer' ? old('meter_number') : null" :label="__('Meter number')" required />
                            <flux:input class="sm:col-span-2" name="installed_at" type="date" :value="old('_form') === 'create-consumer' ? old('installed_at') : null" :label="__('Installation date (optional)')" />
                        </div>
                    </x-form-modal>
                    @foreach ($selectedConsumer && request('edit_consumer') ? [$selectedConsumer] : [] as $consumer)
                        <x-form-modal :open="true" :name="'edit-consumer-'.$consumer->id" title="Edit consumer" :action="route('provider.consumers.update', $consumer)" method="PUT" submit-label="Save">
                            <flux:input placeholder="e.g. C-000123" name="account_number" :value="old('_form') === 'edit-consumer-'.$consumer->id ? old('account_number') : $consumer->account_number" label="Account number" required />
                            <flux:input placeholder="Enter the full name" name="name" :value="old('_form') === 'edit-consumer-'.$consumer->id ? old('name') : $consumer->name" label="Name" required />
                            <flux:input placeholder="e.g. 09123456789" name="phone" type="tel" inputmode="numeric" pattern="[0-9]*" :value="old('_form') === 'edit-consumer-'.$consumer->id ? old('phone') : $consumer->phone" label="Phone" />
                            <flux:input placeholder="e.g. consumer@example.com" name="email" :value="old('_form') === 'edit-consumer-'.$consumer->id ? old('email') : $consumer->email" label="Email" type="email" />
                            <flux:input placeholder="House number, street, barangay, city" name="address" :value="old('_form') === 'edit-consumer-'.$consumer->id ? old('address') : $consumer->address" label="Service address" required />
                        </x-form-modal>
                    @endforeach
                    @foreach ($selectedConsumer && request('portal_consumer') && !$selectedConsumer->portalUser ? [$selectedConsumer] : [] as $consumer)
                        <x-form-modal :open="true" :name="'activate-portal-'.$consumer->id" :title="__('Activate portal access')" :description="$consumer->name.' · '.$consumer->account_number" :action="route('provider.consumers.portal', $consumer)" :submit-label="__('Activate portal')">
                            <div class="space-y-4">
                                <flux:input placeholder="e.g. consumer@example.com" name="email" type="email" :value="old('_form') === 'activate-portal-'.$consumer->id ? old('email') : $consumer->email" :label="__('Login email')" required />
                                <flux:input placeholder="Enter a temporary password (at least 12 characters)" name="password" type="password" :label="__('Temporary password')" required autocomplete="new-password" />
                            </div>
                        </x-form-modal>
                    @endforeach
                @endcan
            </section>
        @endcan
@endif
@if ($previewedConsumer)
    @can('permission', 'consumers.view')
        <section id="consumer-details" class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:card><flux:text>{{ __('Contact') }}</flux:text><div class="mt-2 text-sm">{{ $previewedConsumer->phone ?: __('Not recorded') }}<br>{{ $previewedConsumer->email ?: __('Not recorded') }}</div></flux:card>
                <flux:card><flux:text>{{ __('Service address') }}</flux:text><div class="mt-2 text-sm">{{ $previewedConsumer->address }}</div></flux:card>
                <flux:card><flux:text>{{ __('Account status') }}</flux:text><div class="mt-2"><flux:badge :color="$previewedConsumer->is_active ? 'green' : 'zinc'">{{ $previewedConsumer->is_active ? __('Active') : __('Inactive') }}</flux:badge></div><flux:text class="mt-2 text-sm">{{ $previewedConsumer->portalUser ? __('Portal active') : __('Portal not activated') }}</flux:text></flux:card>
            </div>
            @can('permission', 'connections.view')
                <section class="space-y-3"><flux:heading size="base">{{ __('Connections') }}</flux:heading><div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700"><table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700"><thead><tr><th class="px-4 py-3">{{ __('Connection') }}</th><th class="px-4 py-3">{{ __('Meter') }}</th><th class="px-4 py-3">{{ __('Installed') }}</th><th class="px-4 py-3">{{ __('Status') }}</th></tr></thead><tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">@forelse ($previewedConsumer->connections as $connection)<tr><td class="px-4 py-3">{{ $connection->number }}</td><td class="px-4 py-3">{{ $connection->meter_number }}</td><td class="px-4 py-3">{{ $connection->installed_at?->format('M d, Y') ?? __('Not recorded') }}</td><td class="px-4 py-3">{{ ucfirst(str_replace('_', ' ', $connection->status ?? 'unknown')) }}</td></tr>@empty<tr><td colspan="4" class="px-4 py-3">{{ __('No connections recorded.') }}</td></tr>@endforelse</tbody></table></div></section>
            @endcan
            @if (auth()->user()->can('connections.view') || auth()->user()->can('billing.view') || auth()->user()->can('payments.view'))
                <section class="space-y-3">
                    <div class="flex flex-wrap items-end justify-between gap-3"><flux:heading size="base">{{ __('Account activity') }}</flux:heading><form method="GET" class="flex items-end gap-2"><input type="hidden" name="preview_consumer" value="{{ $previewedConsumer->id }}"><flux:input name="period" type="month" :label="__('Month')" :value="request('period')" /><flux:button type="submit">{{ __('Filter') }}</flux:button>@if (request()->filled('period'))<flux:button :href="$consumerDetailUrl" variant="ghost">{{ __('Clear') }}</flux:button>@endif</form></div>
                    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700"><table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700"><thead><tr><th class="px-4 py-3">{{ __('Date') }}</th><th class="px-4 py-3">{{ __('Type') }}</th><th class="px-4 py-3">{{ __('Reference') }}</th><th class="px-4 py-3">{{ __('Details') }}</th><th class="px-4 py-3">{{ __('Amount') }}</th><th class="px-4 py-3">{{ __('Status') }}</th><th class="px-4 py-3">{{ __('Receipt') }}</th></tr></thead><tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">@forelse ($consumerActivity as $activity)@php($record = $activity['record'])<tr><td class="px-4 py-3">{{ $activity['date']->format($activity['type'] === 'payment' ? 'M d, Y' : 'M Y') }}</td><td class="px-4 py-3">{{ ucfirst($activity['type']) }}</td>@if ($activity['type'] === 'reading')<td class="px-4 py-3">{{ $record->connection->meter_number }}</td><td class="px-4 py-3">{{ $record->previous_reading }} → {{ $record->current_reading }} · {{ number_format((float) $record->current_reading - (float) $record->previous_reading, 3) }} m³</td><td class="px-4 py-3">—</td><td class="px-4 py-3">—</td><td class="px-4 py-3">—</td>@elseif ($activity['type'] === 'bill')@php($status = $record->paymentStatus())<td class="px-4 py-3">{{ $record->number }}</td><td class="px-4 py-3">₱{{ number_format((float) $record->total, 2) }} · ₱{{ number_format((float) $record->balance(), 2) }} {{ __('remaining') }}</td><td class="px-4 py-3">₱{{ number_format((float) $record->total, 2) }}</td><td class="px-4 py-3"><flux:badge :color="$status === 'Paid' ? 'green' : ($status === 'Overdue' ? 'red' : ($status === 'Void' ? 'zinc' : 'yellow'))">{{ $status }}</flux:badge></td><td class="px-4 py-3">—</td>@else<td class="px-4 py-3">{{ $record->reference }}</td><td class="px-4 py-3">{{ $record->bill->number }} · {{ ucfirst(str_replace('_', ' ', $record->method)) }}</td><td class="px-4 py-3">₱{{ number_format((float) $record->amount, 2) }}</td><td class="px-4 py-3"><flux:badge :color="$record->status === 'verified' ? 'green' : ($record->status === 'rejected' ? 'red' : 'yellow')">{{ ucfirst($record->status) }}</flux:badge></td><td class="px-4 py-3">@if ($record->status === 'verified')<flux:button size="sm" :href="route('payments.print', $record)" data-document-preview>{{ __('View receipt') }}</flux:button>@else—@endif</td>@endif</tr>@empty<tr><td colspan="7" class="px-4 py-8 text-center">{{ __('No account activity recorded.') }}</td></tr>@endforelse</tbody></table></div>
                </section>
            @endif
        </section>
    @endcan
@endif


</div>
</x-layouts::app>
