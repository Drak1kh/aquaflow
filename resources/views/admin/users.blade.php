<x-layouts::app :title="__('User management')">
    <div class="flex w-full min-w-0 flex-col gap-7">
        <x-page-header :title="__('User management')" :description="__('Create accounts, assign roles, and control access.')">
            <x-slot:actions>
                <flux:modal.trigger name="create-user-account">
                    <flux:button variant="primary" icon="plus">{{ __('Create user account') }}</flux:button>
                </flux:modal.trigger>
            </x-slot:actions>
        </x-page-header>

        <flux:modal name="create-user-account" :show="$errors->any() && old('_form') === 'create-user'" class="w-full max-w-2xl">
            <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-6">
                @csrf
                <input type="hidden" name="_form" value="create-user">

                <div>
                    <flux:heading level="2" size="lg">{{ __('Create user account') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('Assign a role and provide a temporary password. The user will change it after signing in.') }}</flux:text>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input placeholder="Enter the full name" name="name" :value="old('name')" :label="__('Full name')" required />
                    <flux:input placeholder="e.g. account@example.com" name="email" type="email" :value="old('email')" :label="__('Email address')" required />
                    <flux:select name="role" :label="__('Role')" required>
                        <option value="">{{ __('Choose a role') }}</option>
                        @foreach (\App\UserRole::cases() as $role)
                            <option value="{{ $role->value }}" @selected(old('role') === $role->value)>{{ $role->label() }}</option>
                        @endforeach
                    </flux:select>
                    <flux:select name="water_provider_id" label="Provider for provider accounts">
                        <option value="">Choose a provider</option>
                        @foreach ($providers as $provider)<option value="{{ $provider->id }}" @selected(old('water_provider_id') == $provider->id)>{{ $provider->name }}</option>@endforeach
                    </flux:select>
                    <flux:select name="consumer_id" :label="__('Consumer record for portal users')" class="sm:col-span-2">
                        <option value="">{{ __('None') }}</option>
                        @foreach ($consumers as $consumer)
                            <option value="{{ $consumer->id }}" @selected(old('consumer_id') == $consumer->id)>{{ $consumer->name }} · {{ $consumer->account_number }} · {{ $consumer->waterProvider->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:input placeholder="At least 12 characters" name="password" type="password" :label="__('Temporary password')" required autocomplete="new-password" />
                    <flux:input placeholder="Re-enter the temporary password" name="password_confirmation" type="password" :label="__('Confirm temporary password')" required autocomplete="new-password" />
                </div>

                <div class="flex flex-wrap justify-end gap-2">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('Create account') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        <form method="GET" class="flex flex-wrap gap-3">
            <flux:input name="search" :value="request('search')" :placeholder="__('Search name or email')" icon="magnifying-glass" />
            <flux:select name="role" aria-label="{{ __('Filter by role') }}"><option value="">{{ __('All roles') }}</option>
@foreach (\App\UserRole::cases() as $role)<option value="{{ $role->value }}" @selected(request('role') === $role->value)>{{ $role->label() }}</option>
@endforeach</flux:select>
            <flux:button type="submit">{{ __('Filter') }}</flux:button>
        </form>

        <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
                <thead class="bg-brand-surface text-brand-ink dark:bg-zinc-800 dark:text-white"><tr><th scope="col" class="px-5 py-3">{{ __('User') }}</th><th scope="col" class="px-5 py-3">{{ __('Role') }}</th><th scope="col" class="px-5 py-3">{{ __('Provider') }}</th><th scope="col" class="px-5 py-3">{{ __('Status') }}</th><th scope="col" class="px-5 py-3">{{ __('Actions') }}</th></tr></thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($users as $user)
                        <tr>
                            <td class="px-5 py-4">
                                <a class="font-medium text-brand-primary hover:underline" href="{{ route('admin.users.show', $user) }}">{{ $user->name }}</a>
                                <div class="text-zinc-500">{{ $user->email }}</div>
                            </td>
                            <td class="px-5 py-4">{{ $user->role->label() }}</td>
                            <td class="px-5 py-4">{{ $user->waterProvider?->name ?? '—' }}</td>
                            <td class="px-5 py-4">
                                <flux:badge :color="$user->is_active ? 'green' : 'zinc'">{{ $user->is_active ? __('Active') : __('Inactive') }}</flux:badge>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap gap-2">
                                    <flux:modal.trigger :name="'edit-user-'.$user->id">
                                        <flux:button class="min-h-11" variant="subtle">{{ __('Edit') }}</flux:button>
                                    </flux:modal.trigger>
                                    <form method="POST" action="{{ route('admin.users.status', $user) }}">
                                        @csrf
                                        @method('PATCH')
                                        <flux:button type="submit" class="min-h-11" :variant="$user->is_active ? 'danger' : 'subtle'">{{ $user->is_active ? __('Deactivate') : __('Activate') }}</flux:button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.users.reset', $user) }}">
                                        @csrf
                                        <flux:button type="submit" class="min-h-11">{{ __('Reset password') }}</flux:button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-zinc-600 dark:text-zinc-300">{{ __('No users match these filters.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @foreach ($users as $user)
            @php
                $editingThisUser = old('_form') === 'edit-user-'.$user->id;
                $selectedRole = $editingThisUser ? old('role') : $user->role->value;
                $selectedProvider = $editingThisUser ? old('water_provider_id') : $user->water_provider_id;
                $selectedConsumer = $editingThisUser ? old('consumer_id') : $user->consumer_id;
            @endphp
            <flux:modal :name="'edit-user-'.$user->id" :show="$errors->any() && $editingThisUser" class="w-full max-w-2xl">
                <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-6">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_form" value="edit-user-{{ $user->id }}">

                    <div>
                        <flux:heading level="2" size="lg">{{ __('Edit account') }}</flux:heading>
                        <flux:text class="mt-2">{{ $user->name }} · {{ $user->email }}</flux:text>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input placeholder="Enter the full name" name="name" :value="$editingThisUser ? old('name') : $user->name" :label="__('Full name')" required />
                        <flux:input placeholder="e.g. account@example.com" name="email" type="email" :value="$editingThisUser ? old('email') : $user->email" :label="__('Email address')" required />
                        <flux:select name="role" :label="__('Role')" required>
                            @foreach (\App\UserRole::cases() as $role)
                                <option value="{{ $role->value }}" @selected($selectedRole === $role->value)>{{ $role->label() }}</option>
                            @endforeach
                        </flux:select>
                        <flux:select name="water_provider_id" label="Provider for provider accounts">
                            <option value="">Choose a provider</option>
                            @foreach ($providers as $provider)<option value="{{ $provider->id }}" @selected($selectedProvider == $provider->id)>{{ $provider->name }}</option>@endforeach
                        </flux:select>
                        <flux:select name="consumer_id" :label="__('Consumer record for portal users')" class="sm:col-span-2">
                            <option value="">{{ __('None') }}</option>
                            @foreach ($consumers as $consumer)
                                <option value="{{ $consumer->id }}" @selected($selectedConsumer == $consumer->id)>{{ $consumer->name }} · {{ $consumer->account_number }} · {{ $consumer->waterProvider->name }}</option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2">
                        <flux:modal.close>
                            <flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button>
                    </div>
                </form>
            </flux:modal>
        @endforeach

        {{ $users->links() }}
    </div>
</x-layouts::app>
