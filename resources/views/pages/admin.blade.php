<x-layouts::app :title="__('Platform administration')">
    <main class="mx-auto flex w-full max-w-7xl flex-col gap-8 p-6 text-[#062E68] dark:text-white">
        <header><flux:heading size="xl">Platform administration</flux:heading><flux:text>Providers, staff access, and role permissions</flux:text></header>
        @include('partials.aquaflow-alerts')

        <div class="grid gap-4 sm:grid-cols-3"><flux:card><flux:text>Providers</flux:text><flux:heading size="xl">{{ $providerCount }}</flux:heading></flux:card><flux:card><flux:text>Consumers</flux:text><flux:heading size="xl">{{ $consumerCount }}</flux:heading></flux:card><flux:card><flux:text>Bills generated</flux:text><flux:heading size="xl">{{ $billCount }}</flux:heading></flux:card></div>

        <div class="grid gap-6 lg:grid-cols-2">
            <flux:card class="space-y-4">
                <flux:heading>Create provider</flux:heading>
                <form method="POST" action="{{ route('admin.providers.store') }}" class="space-y-4">
                    @csrf
                    <flux:input name="name" label="Provider name" required />
                    <flux:input name="code" label="Provider code" required />
                    <flux:input name="email" label="Office email" type="email" />
                    <flux:button type="submit" variant="primary">Create provider</flux:button>
                </form>
            </flux:card>
            <flux:card class="space-y-4">
                <flux:heading>Invite provider staff</flux:heading>
                <form method="POST" action="{{ route('admin.staff.invite') }}" class="space-y-4">
                    @csrf
                    <flux:select name="water_provider_id" label="Provider" required>
                        @foreach ($providers as $provider)
                            <flux:select.option value="{{ $provider->id }}">{{ $provider->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select name="role_id" label="Role" required>
                        @foreach ($roles as $role)
                            <flux:select.option value="{{ $role->id }}">{{ $role->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input name="name" label="Name" required />
                    <flux:input name="email" label="Email" type="email" required />
                    <flux:button type="submit" variant="primary">Send invitation</flux:button>
                </form>
            </flux:card>
        </div>

        <flux:card class="space-y-4">
            <flux:heading>Providers</flux:heading>
            <div class="overflow-x-auto"><flux:table>
                <flux:table.columns><flux:table.column>Name</flux:table.column><flux:table.column>Code</flux:table.column><flux:table.column>Consumers</flux:table.column><flux:table.column>Status</flux:table.column><flux:table.column>Open workspace</flux:table.column></flux:table.columns>
                <flux:table.rows>
                    @foreach ($providers as $provider)
                        <flux:table.row>
                            <flux:table.cell>{{ $provider->name }}</flux:table.cell>
                            <flux:table.cell>{{ $provider->code }}</flux:table.cell>
                            <flux:table.cell>{{ $provider->consumers_count }}</flux:table.cell>
                            <flux:table.cell>
                                <form method="POST" action="{{ route('admin.providers.update', $provider) }}" class="flex flex-wrap items-center gap-2">@csrf @method('PUT')
                                    <input name="name" value="{{ $provider->name }}" aria-label="Provider name" class="w-36 rounded border p-1 dark:bg-zinc-800" required>
                                    <input name="email" value="{{ $provider->email }}" aria-label="Provider email" type="email" class="w-36 rounded border p-1 dark:bg-zinc-800">
                                    <select name="status" aria-label="Provider status" class="rounded border p-1 dark:bg-zinc-800"><option value="active" @selected($provider->status === 'active')>Active</option><option value="inactive" @selected($provider->status === 'inactive')>Inactive</option></select>
                                    <flux:button type="submit" size="sm">Save</flux:button>
                                </form>
                            </flux:table.cell>
                            <flux:table.cell><form method="POST" action="{{ route('providers.switch') }}">@csrf<input type="hidden" name="water_provider_id" value="{{ $provider->id }}"><flux:button type="submit" size="sm">Select</flux:button></form></flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table></div>
        </flux:card>

        <flux:card class="space-y-4"><flux:heading>Users</flux:heading>
            <div class="overflow-x-auto"><flux:table><flux:table.columns><flux:table.column>Name</flux:table.column><flux:table.column>Email</flux:table.column><flux:table.column>Provider memberships</flux:table.column><flux:table.column>Consumer accounts</flux:table.column><flux:table.column>Status</flux:table.column></flux:table.columns><flux:table.rows>
                @foreach ($users as $user)
                    <flux:table.row><flux:table.cell>{{ $user->name }}</flux:table.cell><flux:table.cell>{{ $user->email }}</flux:table.cell><flux:table.cell>{{ $user->memberships_count }}</flux:table.cell><flux:table.cell>{{ $user->consumers_count }}</flux:table.cell><flux:table.cell>
                        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="flex gap-2">@csrf @method('PUT')<select name="status" aria-label="User status" class="rounded border p-1 dark:bg-zinc-800"><option value="active" @selected($user->status === 'active')>Active</option><option value="inactive" @selected($user->status === 'inactive')>Inactive</option><option value="suspended" @selected($user->status === 'suspended')>Suspended</option></select><flux:button type="submit" size="sm">Save</flux:button></form>
                    </flux:table.cell></flux:table.row>
                @endforeach
            </flux:table.rows></flux:table></div>{{ $users->links() }}
        </flux:card>

        <flux:card class="space-y-4"><flux:heading>Create role</flux:heading>
            <form method="POST" action="{{ route('admin.roles.store') }}" class="flex flex-wrap items-end gap-3">@csrf<flux:input name="name" label="Role name" required /><flux:input name="slug" label="Code" required /><flux:button type="submit" variant="primary">Create role</flux:button></form>
        </flux:card>

        <div class="grid gap-6 lg:grid-cols-2">
            @foreach ($roles as $role)
                <flux:card class="space-y-4">
                    <flux:heading>{{ $role->name }} permissions</flux:heading>
                    <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="space-y-4">
                        @csrf @method('PUT')
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($permissions as $permission)
                                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked($role->permissions->contains($permission))>{{ $permission->code }}</label>
                            @endforeach
                        </div>
                        <flux:button type="submit" variant="primary">Save permissions</flux:button>
                    </form>
                </flux:card>
            @endforeach
        </div>
    </main>
</x-layouts::app>
