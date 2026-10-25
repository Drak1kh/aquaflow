<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Roles and permissions')] class extends Component {
    use WithPagination;

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

    public function sort(string $column): void
    {
        $this->authorizeAdmin();
        abort_unless(in_array($column, ['name', 'permissions_count'], true), 422);

        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    #[Computed]
    public function roles(): LengthAwarePaginator
    {
        $this->authorizeAdmin();
        $column = in_array($this->sortBy, ['name', 'permissions_count'], true) ? $this->sortBy : 'name';
        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return Role::query()->with('permissions')->withCount('permissions')->orderBy($column, $direction)->orderBy('id')->paginate(10);
    }

    /** @return Collection<string, Collection<int, Permission>> */
    #[Computed]
    public function permissionsByModule(): Collection
    {
        return Permission::orderBy('code')->get()->groupBy(fn (Permission $permission) => explode('.', $permission->code)[0]);
    }
}; ?>

    <main class="w-full min-w-0 space-y-7 px-4 py-8 text-[#062E68] sm:px-6 lg:px-8 dark:text-white">
        <header class="flex flex-col gap-5 border-b border-[#BDECF8] pb-7 sm:flex-row sm:items-end sm:justify-between dark:border-slate-700">
            <div class="space-y-2"><flux:heading size="xl">Roles & permissions</flux:heading><flux:text>Control the actions provider staff can take in each module.</flux:text></div>
            <flux:modal.trigger name="create-role"><flux:button variant="primary" icon="plus">Create role</flux:button></flux:modal.trigger>
        </header>

        @include('partials.aquaflow-alerts')

        <div class="rounded-2xl bg-[#EAF8FC] px-5 py-4 text-sm leading-relaxed text-[#062E68] dark:bg-slate-800 dark:text-slate-100">
            Permissions apply to a user's role within a provider. Super Admins have platform-wide access. Changes to a role take effect for every member assigned to it.
        </div>

        <section class="space-y-4" aria-labelledby="roles-heading">
            <div><flux:heading id="roles-heading" size="lg">Provider roles</flux:heading><flux:text>Select a role to review or change its module permissions.</flux:text></div>
            <div class="min-w-0">
                <flux:table>
                    <flux:table.columns><flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">Role</flux:table.column><flux:table.column>Access summary</flux:table.column><flux:table.column sortable :sorted="$sortBy === 'permissions_count'" :direction="$sortDirection" wire:click="sort('permissions_count')">Permissions</flux:table.column><flux:table.column>Action</flux:table.column></flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->roles as $role)
                            <flux:table.row :key="$role->id">
                                <flux:table.cell><p class="font-semibold text-[#062E68] dark:text-white">{{ $role->name }}</p><p class="text-xs text-slate-500 dark:text-slate-400">{{ $role->slug }}</p></flux:table.cell>
                                <flux:table.cell class="text-sm">{{ $role->permissions->pluck('code')->map(fn ($code) => explode('.', $code)[0])->unique()->map(fn ($module) => ucfirst($module))->join(', ') ?: 'No module access' }}</flux:table.cell>
                                <flux:table.cell class="tabular-nums">{{ $role->permissions_count }}</flux:table.cell>
                                <flux:table.cell><flux:modal.trigger :name="'edit-role-'.$role->id"><flux:button size="sm">Edit permissions</flux:button></flux:modal.trigger></flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row><flux:table.cell colspan="4" class="py-14 text-center">No roles yet. Create one to define staff access.</flux:table.cell></flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
                @include('partials.admin-table-pagination', ['paginator' => $this->roles])
            </div>
        </section>

        <flux:modal name="create-role" class="w-full max-w-2xl" scroll="body">
            <form method="POST" action="{{ route('admin.roles.store') }}" class="space-y-6">
                @csrf
                <input type="hidden" name="_modal" value="create-role">
                <div class="space-y-1"><flux:heading size="lg">Create role</flux:heading><flux:text>Name the role and choose the actions its members can perform.</flux:text></div>
                <div class="grid gap-4 sm:grid-cols-2"><flux:input name="name" label="Role name" placeholder="e.g. Billing officer" :value="old('name')" required /><flux:input name="slug" label="Role code" placeholder="e.g. billing_officer" :value="old('slug')" required /></div>
                <div class="space-y-4">
                    <flux:heading>Permissions</flux:heading>
                    @foreach ($this->permissionsByModule as $module => $modulePermissions)
                        <fieldset class="border-t border-[#BDECF8] pt-4 dark:border-slate-700">
                            <legend class="font-semibold capitalize">{{ $module }}</legend>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                @foreach ($modulePermissions as $permission)
                                    <flux:checkbox name="permissions[]" :value="$permission->id" :label="$permission->name" :checked="in_array($permission->id, old('permissions', []))" />
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                </div>
                <div class="flex justify-end gap-2"><flux:modal.close><flux:button>Cancel</flux:button></flux:modal.close><flux:button type="submit" variant="primary">Create role</flux:button></div>
            </form>
        </flux:modal>

        @foreach ($this->roles as $role)
            <flux:modal :name="'edit-role-'.$role->id" class="w-full max-w-2xl" scroll="body">
                <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="space-y-6">
                    @csrf @method('PUT')
                    <input type="hidden" name="_modal" value="edit-role-{{ $role->id }}">
                    <div class="space-y-1"><flux:heading size="lg">Edit {{ $role->name }}</flux:heading><flux:text>These permissions apply to every provider member with this role.</flux:text></div>
                    <flux:input name="name" label="Role name" :value="old('_modal') === 'edit-role-'.$role->id ? old('name') : $role->name" required />
                    <p class="text-xs text-slate-500 dark:text-slate-400">Role code: {{ $role->slug }}</p>
                    <div class="space-y-4">
                        <flux:heading>Module permissions</flux:heading>
                        @foreach ($this->permissionsByModule as $module => $modulePermissions)
                            <fieldset class="border-t border-[#BDECF8] pt-4 dark:border-slate-700">
                                <legend class="font-semibold capitalize">{{ $module }}</legend>
                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    @foreach ($modulePermissions as $permission)
                                        <flux:checkbox name="permissions[]" :value="$permission->id" :label="$permission->name" :checked="old('_modal') === 'edit-role-'.$role->id ? in_array($permission->id, old('permissions', [])) : $role->permissions->contains($permission)" />
                                    @endforeach
                                </div>
                            </fieldset>
                        @endforeach
                    </div>
                    <div class="flex justify-end gap-2"><flux:modal.close><flux:button>Cancel</flux:button></flux:modal.close><flux:button type="submit" variant="primary">Save permissions</flux:button></div>
                </form>
            </flux:modal>
        @endforeach

        @if ($errors->any() && old('_modal'))<div x-data x-init="$nextTick(() => $dispatch('modal-show', { name: @js(old('_modal')) }))"></div>@endif
    </main>
