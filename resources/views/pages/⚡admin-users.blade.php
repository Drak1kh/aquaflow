<?php

use App\Models\Role;
use App\Models\User;
use App\Models\WaterProvider;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('User management')] class extends Component {
    use WithPagination;

    #[Url(as: 'search')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = '';

    #[Locked]
    public string $sortBy = 'name';

    #[Locked]
    public string $sortDirection = 'asc';

    public function mount(): void
    {
        $this->authorizeAdmin();
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->is_super_admin && auth()->user()?->status === 'active', 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function sort(string $column): void
    {
        $this->authorizeAdmin();
        abort_unless(in_array($column, ['name', 'email', 'status', 'created_at'], true), 422);

        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    #[Computed]
    public function users(): LengthAwarePaginator
    {
        $this->authorizeAdmin();
        abort_if(mb_strlen($this->search) > 100, 422);
        abort_unless(in_array($this->statusFilter, ['', 'active', 'inactive', 'suspended'], true), 422);

        $search = trim($this->search);
        $column = in_array($this->sortBy, ['name', 'email', 'status', 'created_at'], true) ? $this->sortBy : 'name';
        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return User::query()
            ->with(['role', 'memberships.provider', 'memberships.role'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%');
            }))
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->orderBy($column, $direction)
            ->orderBy('id')
            ->paginate(10);
    }

    /** @return Collection<int, Role> */
    #[Computed]
    public function roles(): Collection
    {
        return Role::orderBy('name')->get();
    }

    /** @return Collection<int, WaterProvider> */
    #[Computed]
    public function providers(): Collection
    {
        return WaterProvider::where('status', 'active')->orderBy('name')->get();
    }

    #[Computed]
    public function totalUsers(): int
    {
        return User::count();
    }

    #[Computed]
    public function activeUsers(): int
    {
        return User::where('status', 'active')->count();
    }

    #[Computed]
    public function usersWithRoles(): int
    {
        return User::whereNotNull('role_id')->count();
    }
}; ?>

    <main class="w-full min-w-0 space-y-7 py-8 text-[#062E68] dark:text-white">
        <header class="flex flex-col gap-5 border-b border-[#BDECF8] pb-7 sm:flex-row sm:items-end sm:justify-between dark:border-slate-700">
            <div class="space-y-2">
                <flux:heading size="xl">User management</flux:heading>
                <flux:text>Create accounts, assign roles, and manage access status.</flux:text>
            </div>
            <flux:modal.trigger name="create-user"><flux:button variant="primary" icon="plus">Create user</flux:button></flux:modal.trigger>
        </header>

        @include('partials.aquaflow-alerts')

        <div class="flex flex-wrap gap-x-8 gap-y-2 border-b border-[#BDECF8] pb-5 text-sm dark:border-slate-700">
            <p><strong class="mr-2 text-xl tabular-nums">{{ $this->totalUsers }}</strong> total accounts</p>
            <p><strong class="mr-2 text-xl tabular-nums">{{ $this->activeUsers }}</strong> active</p>
            <p><strong class="mr-2 text-xl tabular-nums">{{ $this->usersWithRoles }}</strong> with account roles</p>
        </div>

        <section aria-labelledby="directory-heading" class="space-y-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div><flux:heading id="directory-heading" size="lg">Account directory</flux:heading><flux:text>Search by name or email, then open an account to change access.</flux:text></div>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                    <flux:input wire:model.live.debounce.300ms="search" type="search" label="Search users" placeholder="Name or email" maxlength="100" />
                    <flux:select wire:model.live="statusFilter" label="Status" class="sm:w-40">
                        <flux:select.option value="">All statuses</flux:select.option>
                        <flux:select.option value="active">Active</flux:select.option>
                        <flux:select.option value="inactive">Inactive</flux:select.option>
                        <flux:select.option value="suspended">Suspended</flux:select.option>
                    </flux:select>
                    <flux:button wire:click="clearFilters" :disabled="$search === '' && $statusFilter === ''">Clear</flux:button>
                </div>
            </div>

            <div class="min-w-0">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">User</flux:table.column>
                        <flux:table.column>Account role</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')">Status</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">Joined</flux:table.column>
                        <flux:table.column>Action</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->users as $user)
                            <flux:table.row :key="$user->id">
                                <flux:table.cell>
                                    <div class="flex items-center gap-3">
                                        <flux:avatar :name="$user->name" :initials="$user->initials()" size="sm" />
                                        <div class="min-w-0"><p class="font-semibold text-[#062E68] dark:text-white">{{ $user->name }}</p><p class="max-w-60 truncate text-xs text-slate-500 dark:text-slate-400">{{ $user->email }}</p></div>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($user->is_super_admin)
                                        <flux:badge color="blue" size="sm">Super Admin</flux:badge>
                                    @elseif ($user->role)
                                        <flux:badge color="blue" size="sm">{{ $user->role->name }}</flux:badge>
                                    @else
                                        <span class="text-sm text-slate-500 dark:text-slate-400">No account role</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell><flux:badge :color="$user->status === 'active' ? 'green' : ($user->status === 'suspended' ? 'amber' : 'zinc')" size="sm">{{ ucfirst($user->status) }}</flux:badge></flux:table.cell>
                                <flux:table.cell class="text-sm tabular-nums">{{ $user->created_at?->format('M j, Y') }}</flux:table.cell>
                                <flux:table.cell><flux:modal.trigger :name="'manage-user-'.$user->id"><flux:button size="sm">Manage</flux:button></flux:modal.trigger></flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row><flux:table.cell colspan="5" class="py-14 text-center">No accounts match these filters.</flux:table.cell></flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
                @include('partials.admin-table-pagination', ['paginator' => $this->users])
            </div>
        </section>

        <flux:modal name="create-user" class="w-full max-w-xl">
            <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-5">
                @csrf
                <input type="hidden" name="_modal" value="create-user">
                <div class="space-y-1"><flux:heading size="lg">Create user</flux:heading><flux:text>The user will receive an email to set a password and verify their address.</flux:text></div>
                <div class="grid gap-4 sm:grid-cols-2"><flux:input name="name" label="Full name" :value="old('name')" required /><flux:input name="email" label="Email address" type="email" :value="old('email')" required /></div>
                <flux:select name="role_id" label="Account role">
                    <flux:select.option value="">Assign later</flux:select.option>
                    @foreach ($this->roles as $role)<flux:select.option :value="$role->id" :selected="old('role_id') == $role->id">{{ $role->name }}</flux:select.option>@endforeach
                </flux:select>
                <flux:text class="text-xs">Provider permissions are assigned after the account is created.</flux:text>
                <div class="flex justify-end gap-2"><flux:modal.close><flux:button>Cancel</flux:button></flux:modal.close><flux:button type="submit" variant="primary">Create & invite</flux:button></div>
            </form>
        </flux:modal>

        @foreach ($this->users as $user)
            <flux:modal :name="'manage-user-'.$user->id" class="w-full max-w-3xl" scroll="body">
                <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-5">
                    @csrf @method('PUT')
                    <input type="hidden" name="_modal" value="manage-user-{{ $user->id }}">
                    <div class="space-y-1"><flux:heading size="lg">Manage {{ $user->name }}</flux:heading><flux:text>Update account details, status, and role.</flux:text></div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input name="name" label="Full name" :value="old('_modal') === 'manage-user-'.$user->id ? old('name') : $user->name" required />
                        <flux:input name="email" label="Email address" type="email" :value="old('_modal') === 'manage-user-'.$user->id ? old('email') : $user->email" required />
                    </div>
                    <flux:select name="status" label="Account status" :disabled="$user->id === auth()->id()">
                        @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended'] as $value => $label)
                            <flux:select.option :value="$value" :selected="(old('_modal') === 'manage-user-'.$user->id ? old('status') : $user->status) === $value">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @if ($user->id === auth()->id())<input type="hidden" name="status" value="active"><flux:text class="text-xs">Your own account cannot be deactivated.</flux:text>@endif
                    @if ($user->is_super_admin)
                        <flux:callout icon="shield-check">This account has Super Admin access.</flux:callout>
                    @else
                        <flux:select name="role_id" label="Account role">
                            <flux:select.option value="" :selected="blank(old('_modal') === 'manage-user-'.$user->id ? old('role_id') : $user->role_id)">No account role</flux:select.option>
                            @foreach ($this->roles as $role)
                                <flux:select.option :value="$role->id" :selected="(old('_modal') === 'manage-user-'.$user->id ? old('role_id') : $user->role_id) == $role->id">{{ $role->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:text class="text-xs">This label does not grant provider access. Assign a provider role below.</flux:text>
                    @endif
                    <div class="flex justify-end gap-2"><flux:modal.close><flux:button>Cancel</flux:button></flux:modal.close><flux:button type="submit" variant="primary">Save changes</flux:button></div>
                </form>
                <div class="mt-6 space-y-4 border-t border-[#BDECF8] pt-5 dark:border-zinc-700">
                    <div><flux:heading>Provider access</flux:heading><flux:text>Assign a provider role to control this user's operational permissions.</flux:text></div>
                    @foreach ($user->memberships as $membership)
                        <form method="POST" action="{{ route('admin.users.memberships.store', $user) }}" class="grid gap-3 rounded-xl border border-[#BDECF8] p-4 sm:grid-cols-3 dark:border-zinc-700">
                            @csrf
                            <input type="hidden" name="_modal" value="manage-user-{{ $user->id }}">
                            <input type="hidden" name="water_provider_id" value="{{ $membership->water_provider_id }}">
                            <p class="self-end text-sm font-semibold">{{ $membership->provider->name }}</p>
                            <flux:select name="role_id" label="Provider role">
                                @foreach ($this->roles as $role)
                                    <flux:select.option :value="$role->id" :selected="$membership->role_id === $role->id">{{ $role->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:select name="status" label="Access">
                                <flux:select.option value="active" :selected="$membership->status === 'active'">Active</flux:select.option>
                                <flux:select.option value="inactive" :selected="$membership->status === 'inactive'">Inactive</flux:select.option>
                            </flux:select>
                            <flux:button type="submit" size="sm" class="sm:col-start-3">Save provider access</flux:button>
                        </form>
                    @endforeach
                    @if ($this->providers->contains(fn ($provider) => ! $user->memberships->contains('water_provider_id', $provider->id)))
                        <form method="POST" action="{{ route('admin.users.memberships.store', $user) }}" class="grid gap-3 rounded-xl border border-[#BDECF8] p-4 sm:grid-cols-3 dark:border-zinc-700">
                            @csrf
                            <input type="hidden" name="_modal" value="manage-user-{{ $user->id }}">
                            <flux:select name="water_provider_id" label="Add provider" required>
                                @foreach ($this->providers as $provider)
                                    @unless ($user->memberships->contains('water_provider_id', $provider->id))
                                        <flux:select.option :value="$provider->id">{{ $provider->name }}</flux:select.option>
                                    @endunless
                                @endforeach
                            </flux:select>
                            <flux:select name="role_id" label="Provider role" required>
                                @foreach ($this->roles as $role)
                                    <flux:select.option :value="$role->id">{{ $role->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <input type="hidden" name="status" value="active">
                            <flux:button type="submit" size="sm" class="sm:self-end">Assign provider role</flux:button>
                        </form>
                    @endif
                </div>
            </flux:modal>
        @endforeach

        @if ($errors->any() && old('_modal'))<div x-data x-init="$nextTick(() => $dispatch('modal-show', { name: @js(old('_modal')) }))"></div>@endif
    </main>
