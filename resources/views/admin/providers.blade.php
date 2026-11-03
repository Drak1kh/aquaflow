<x-layouts::app :title="__('Water providers')">
    <div class="flex w-full min-w-0 flex-col gap-7">
        <x-page-header :title="__('Water providers')" :description="__('Provider organizations and their access to AquaFlow.')">
            <x-slot:actions>
                <flux:modal.trigger name="create-provider">
                    <flux:button variant="primary" icon="plus">{{ __('Register provider') }}</flux:button>
                </flux:modal.trigger>
            </x-slot:actions>
        </x-page-header>

        <x-form-modal name="create-provider" :title="__('Register provider')" :action="route('admin.providers.store')" :submit-label="__('Register provider')">
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input placeholder="Enter the full name" name="name" :value="old('_form') === 'create-provider' ? old('name') : null" :label="__('Provider name')" required />
                <flux:input placeholder="e.g. north-water" name="code" :value="old('_form') === 'create-provider' ? old('code') : null" :label="__('Unique code')" required />
                <flux:input placeholder="e.g. account@example.com" name="email" type="email" :value="old('_form') === 'create-provider' ? old('email') : null" :label="__('Email')" />
                <flux:input placeholder="e.g. 09123456789" name="phone" :value="old('_form') === 'create-provider' ? old('phone') : null" :label="__('Phone')" />
                <div class="sm:col-span-2">
                    <flux:textarea placeholder="Enter the office or service address" name="address" :label="__('Address')">{{ old('_form') === 'create-provider' ? old('address') : '' }}</flux:textarea>
                </div>
            </div>
        </x-form-modal>

        <form method="GET" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <flux:input name="search" :value="request('search')" :placeholder="__('Search providers')" icon="magnifying-glass" />
            <flux:button type="submit">{{ __('Search') }}</flux:button>
        </form>

        <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
                <thead class="bg-brand-surface text-brand-ink dark:bg-zinc-800 dark:text-white">
                    <tr>
                        <th scope="col" class="px-5 py-3">{{ __('Provider') }}</th>
                        <th scope="col" class="px-5 py-3">{{ __('Consumers') }}</th>
                        <th scope="col" class="px-5 py-3">{{ __('Staff') }}</th>
                        <th scope="col" class="px-5 py-3">{{ __('Status') }}</th>
                        <th scope="col" class="px-5 py-3">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($providers as $provider)
                        <tr>
                            <td class="px-5 py-4"><div class="font-medium">{{ $provider->name }}</div><div class="text-zinc-500">{{ $provider->code }}</div></td>
                            <td class="px-5 py-4 tabular-nums">{{ $provider->consumers_count }}</td>
                            <td class="px-5 py-4 tabular-nums">{{ $provider->users_count }}</td>
                            <td class="px-5 py-4"><flux:badge :color="$provider->is_active ? 'green' : 'zinc'">{{ $provider->is_active ? __('Active') : __('Inactive') }}</flux:badge></td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap gap-2">
                                    <flux:button :href="route('dashboard.provider', ['provider_context' => $provider->id])">Open operations</flux:button>
                                    <flux:modal.trigger :name="'edit-provider-'.$provider->id">
                                        <flux:button class="min-h-11" variant="subtle">{{ __('Edit') }}</flux:button>
                                    </flux:modal.trigger>
                                    <form method="POST" action="{{ route('admin.providers.status', $provider) }}">
                                        @csrf
                                        @method('PATCH')
                                        <flux:button class="min-h-11" type="submit">{{ $provider->is_active ? __('Deactivate') : __('Activate') }}</flux:button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-zinc-600 dark:text-zinc-300">{{ __('No providers found. Register one to start provider operations.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @foreach ($providers as $provider)
            @php($editingThisProvider = old('_form') === 'edit-provider-'.$provider->id)
            <x-form-modal :name="'edit-provider-'.$provider->id" :title="__('Edit provider')" :description="$provider->name" :action="route('admin.providers.update', $provider)" method="PUT" :submit-label="__('Save provider')">
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input placeholder="Enter the full name" name="name" :value="$editingThisProvider ? old('name') : $provider->name" :label="__('Provider name')" required />
                    <flux:input placeholder="e.g. north-water" name="code" :value="$editingThisProvider ? old('code') : $provider->code" :label="__('Unique code')" required />
                    <flux:input placeholder="e.g. account@example.com" name="email" type="email" :value="$editingThisProvider ? old('email') : $provider->email" :label="__('Email')" />
                    <flux:input placeholder="e.g. 09123456789" name="phone" :value="$editingThisProvider ? old('phone') : $provider->phone" :label="__('Phone')" />
                    <div class="sm:col-span-2">
                        <flux:input placeholder="Enter the office or service address" name="address" :value="$editingThisProvider ? old('address') : $provider->address" :label="__('Address')" />
                    </div>
                    <flux:input placeholder="e.g. 14" name="due_days" type="number" min="0" max="90" :value="$editingThisProvider ? old('due_days') : $provider->due_days" :label="__('Due days override')" />
                    <flux:input placeholder="e.g. 7" name="grace_days" type="number" min="0" max="90" :value="$editingThisProvider ? old('grace_days') : $provider->grace_days" :label="__('Grace days override')" />
                    <flux:input placeholder="e.g. 20.00" name="late_fee" type="number" min="0" step="0.01" :value="$editingThisProvider ? old('late_fee') : $provider->late_fee" :label="__('Late fee override (PHP)')" />
                </div>
            </x-form-modal>
        @endforeach

        {{ $providers->links() }}
    </div>
</x-layouts::app>
