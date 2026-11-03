<x-layouts::app title="Consumer Management">
<div class="provider-page w-full min-w-0 space-y-6">
<x-page-header title="Consumer Management" />

<form method="GET" class="flex flex-col gap-3 sm:flex-row sm:items-end"><flux:input name="search" label="Search consumer name or account" :value="request('search')" /><flux:button type="submit">Search</flux:button></form>
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
                        <thead class="bg-brand-surface text-brand-ink dark:bg-zinc-800 dark:text-white"><tr><th scope="col" class="px-4 py-3">{{ __('Account') }}</th><th scope="col" class="px-4 py-3">{{ __('Consumer') }}</th><th scope="col" class="px-4 py-3">{{ __('Contact') }}</th><th scope="col" class="px-4 py-3">{{ __('Service address') }}</th><th scope="col" class="px-4 py-3">{{ __('Portal') }}</th><th scope="col" class="px-4 py-3">{{ __('Status') }}</th><th scope="col" class="px-4 py-3">{{ __('Actions') }}</th></tr></thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse ($consumers as $consumer)
                                <tr>
                                    <td class="px-4 py-3">{{ $consumer->account_number }}</td>
                                    <td class="px-4 py-3">{{ $consumer->name }}</td>
                                    <td class="px-4 py-3">{{ $consumer->phone }}<br>{{ $consumer->email }}</td><td class="px-4 py-3">{{ $consumer->address }}</td>
                                    <td class="px-4 py-3">{{ $consumer->portalUser ? __('Active') : __('Not activated') }}</td>
                                    <td class="px-4 py-3">{{ $consumer->is_active ? __('Active') : __('Inactive') }}</td>
                                    <td class="px-4 py-3">
                                        @can('permission', 'consumers.manage')
                                            <div class="flex flex-wrap gap-2">
                                                <flux:button :href="request()->fullUrlWithQuery(['edit_consumer' => $consumer->id, 'portal_consumer' => null])" class="min-h-11">Edit</flux:button>
                                                @if (! $consumer->portalUser)
                                                    <flux:button :href="request()->fullUrlWithQuery(['portal_consumer' => $consumer->id, 'edit_consumer' => null])" class="min-h-11" variant="subtle">{{ __('Activate portal') }}</flux:button>
                                                @endif
                                                <form method="POST" action="{{ route('provider.consumers.status', $consumer) }}">
                                                    @csrf @method('PATCH')
                                                    <flux:button class="min-h-11" type="submit">{{ $consumer->is_active ? __('Deactivate') : __('Activate') }}</flux:button>
                                                </form>
                                            </div>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-4 py-8 text-center">{{ __('Register a consumer to begin.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $consumers->links() }}
                @can('permission', 'consumers.manage')
                    <x-form-modal name="create-consumer" :title="__('Register consumer')" :action="route('provider.consumers.store')" :submit-label="__('Register consumer')">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:input name="account_number" :value="old('_form') === 'create-consumer' ? old('account_number') : null" :label="__('Account number (leave blank to generate)')" />
                            <flux:input name="name" :value="old('_form') === 'create-consumer' ? old('name') : null" :label="__('Full name')" required />
                            <flux:input name="email" type="email" :value="old('_form') === 'create-consumer' ? old('email') : null" :label="__('Email')" />
                            <flux:input name="phone" :value="old('_form') === 'create-consumer' ? old('phone') : null" :label="__('Phone')" />
                            <div class="sm:col-span-2"><flux:input name="address" :value="old('_form') === 'create-consumer' ? old('address') : null" :label="__('Service address')" required /></div>
                        </div>
                    </x-form-modal>
                    @foreach ($selectedConsumer && request('edit_consumer') ? [$selectedConsumer] : [] as $consumer)
                        <x-form-modal :open="true" :name="'edit-consumer-'.$consumer->id" title="Edit consumer" :action="route('provider.consumers.update', $consumer)" method="PUT" submit-label="Save">
                            <flux:input name="account_number" :value="old('_form') === 'edit-consumer-'.$consumer->id ? old('account_number') : $consumer->account_number" label="Account number" required />
                            <flux:input name="name" :value="old('_form') === 'edit-consumer-'.$consumer->id ? old('name') : $consumer->name" label="Name" required />
                            <flux:input name="phone" :value="old('_form') === 'edit-consumer-'.$consumer->id ? old('phone') : $consumer->phone" label="Phone" />
                            <flux:input name="email" :value="old('_form') === 'edit-consumer-'.$consumer->id ? old('email') : $consumer->email" label="Email" type="email" />
                            <flux:input name="address" :value="old('_form') === 'edit-consumer-'.$consumer->id ? old('address') : $consumer->address" label="Service address" required />
                        </x-form-modal>
                    @endforeach
                    @foreach ($selectedConsumer && request('portal_consumer') && !$selectedConsumer->portalUser ? [$selectedConsumer] : [] as $consumer)
                        <x-form-modal :open="true" :name="'activate-portal-'.$consumer->id" :title="__('Activate portal access')" :description="$consumer->name.' · '.$consumer->account_number" :action="route('provider.consumers.portal', $consumer)" :submit-label="__('Activate portal')">
                            <div class="space-y-4">
                                <flux:input name="email" type="email" :value="old('_form') === 'activate-portal-'.$consumer->id ? old('email') : $consumer->email" :label="__('Login email')" required />
                                <flux:input name="password" type="password" :label="__('Temporary password')" required autocomplete="new-password" />
                            </div>
                        </x-form-modal>
                    @endforeach
                @endcan
            </section>
        @endcan


</div>
</x-layouts::app>
