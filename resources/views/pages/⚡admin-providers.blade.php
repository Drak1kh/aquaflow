<?php

use App\Models\WaterProvider;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Providers')] class extends Component {
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
        abort_unless(in_array($column, ['name', 'code', 'consumers_count', 'status'], true), 422);

        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    #[Computed]
    public function providers(): LengthAwarePaginator
    {
        $this->authorizeAdmin();
        $column = in_array($this->sortBy, ['name', 'code', 'consumers_count', 'status'], true) ? $this->sortBy : 'name';
        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return WaterProvider::query()->withCount('consumers')->orderBy($column, $direction)->orderBy('id')->paginate(10);
    }

    #[Computed]
    public function providerCount(): int
    {
        return WaterProvider::count();
    }

    #[Computed]
    public function activeProviderCount(): int
    {
        return WaterProvider::where('status', 'active')->count();
    }
}; ?>

    <main class="w-full min-w-0 space-y-7 py-8 text-[#062E68] dark:text-white">
        <header class="flex flex-col gap-5 border-b border-[#BDECF8] pb-7 sm:flex-row sm:items-end sm:justify-between dark:border-slate-700">
            <div class="space-y-2">
                <flux:heading size="xl">Providers</flux:heading>
                <flux:text>Manage the organizations using AquaFlow.</flux:text>
            </div>
            <flux:modal.trigger name="create-provider"><flux:button variant="primary" icon="plus">Add provider</flux:button></flux:modal.trigger>
        </header>

        @include('partials.aquaflow-alerts')

        <div class="flex flex-wrap gap-x-8 gap-y-2 border-b border-[#BDECF8] pb-5 text-sm dark:border-slate-700">
            <p><strong class="mr-2 text-xl tabular-nums">{{ $this->providerCount }}</strong> total providers</p>
            <p><strong class="mr-2 text-xl tabular-nums">{{ $this->activeProviderCount }}</strong> active</p>
            <p><strong class="mr-2 text-xl tabular-nums">{{ $this->providerCount - $this->activeProviderCount }}</strong> inactive</p>
        </div>

        <section aria-labelledby="provider-directory-heading" class="space-y-5">
            <div>
                <flux:heading id="provider-directory-heading" size="lg">Provider directory</flux:heading>
                <flux:text>Review organization details and open active workspaces.</flux:text>
            </div>
            <div class="min-w-0">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">Provider</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'code'" :direction="$sortDirection" wire:click="sort('code')">Code</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'consumers_count'" :direction="$sortDirection" wire:click="sort('consumers_count')">Consumers</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')">Status</flux:table.column>
                        <flux:table.column>Actions</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->providers as $provider)
                            <flux:table.row :key="$provider->id">
                                <flux:table.cell>
                                    <p class="font-semibold text-[#062E68] dark:text-white">{{ $provider->name }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $provider->email ?: 'No office email' }}</p>
                                </flux:table.cell>
                                <flux:table.cell>{{ $provider->code }}</flux:table.cell>
                                <flux:table.cell class="tabular-nums">{{ $provider->consumers_count }}</flux:table.cell>
                                <flux:table.cell><flux:badge :color="$provider->status === 'active' ? 'green' : 'zinc'" size="sm">{{ ucfirst($provider->status) }}</flux:badge></flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex flex-wrap gap-2">
                                        <flux:modal.trigger :name="'edit-provider-'.$provider->id"><flux:button size="sm">Edit</flux:button></flux:modal.trigger>
                                        <form method="POST" action="{{ route('providers.switch') }}">
                                            @csrf
                                            <input type="hidden" name="water_provider_id" value="{{ $provider->id }}">
                                            <flux:button type="submit" size="sm" variant="ghost" :disabled="$provider->status !== 'active'">Open workspace</flux:button>
                                        </form>
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row><flux:table.cell colspan="5" class="py-14 text-center">No providers yet. Add one to begin.</flux:table.cell></flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
                @include('partials.admin-table-pagination', ['paginator' => $this->providers])
            </div>
        </section>

        <flux:modal name="create-provider" class="w-full max-w-lg">
            <form method="POST" action="{{ route('admin.providers.store') }}" class="space-y-5">
                @csrf
                <input type="hidden" name="_modal" value="create-provider">
                <div class="space-y-1"><flux:heading size="lg">Add provider</flux:heading><flux:text>Create an organization workspace.</flux:text></div>
                <flux:input name="name" label="Provider name" :value="old('_modal') === 'create-provider' ? old('name') : ''" required />
                <flux:input name="code" label="Provider code" :value="old('_modal') === 'create-provider' ? old('code') : ''" required />
                <flux:input name="email" label="Office email" type="email" :value="old('_modal') === 'create-provider' ? old('email') : ''" />
                <div class="flex justify-end gap-2"><flux:modal.close><flux:button>Cancel</flux:button></flux:modal.close><flux:button type="submit" variant="primary">Create provider</flux:button></div>
            </form>
        </flux:modal>

        @foreach ($this->providers as $provider)
            <flux:modal :name="'edit-provider-'.$provider->id" class="w-full max-w-lg">
                <form method="POST" action="{{ route('admin.providers.update', $provider) }}" class="space-y-5">
                    @csrf @method('PUT')
                    <input type="hidden" name="_modal" value="edit-provider-{{ $provider->id }}">
                    <div class="space-y-1"><flux:heading size="lg">Edit {{ $provider->name }}</flux:heading><flux:text>Update details and workspace availability.</flux:text></div>
                    <flux:input name="name" label="Provider name" :value="old('_modal') === 'edit-provider-'.$provider->id ? old('name') : $provider->name" required />
                    <flux:input name="email" label="Office email" type="email" :value="old('_modal') === 'edit-provider-'.$provider->id ? old('email') : $provider->email" />
                    <flux:select name="status" label="Status" required>
                        <flux:select.option value="active" :selected="(old('_modal') === 'edit-provider-'.$provider->id ? old('status') : $provider->status) === 'active'">Active</flux:select.option>
                        <flux:select.option value="inactive" :selected="(old('_modal') === 'edit-provider-'.$provider->id ? old('status') : $provider->status) === 'inactive'">Inactive</flux:select.option>
                    </flux:select>
                    <div class="flex justify-end gap-2"><flux:modal.close><flux:button>Cancel</flux:button></flux:modal.close><flux:button type="submit" variant="primary">Save changes</flux:button></div>
                </form>
            </flux:modal>
        @endforeach

        @if ($errors->any() && old('_modal'))<div x-data x-init="$nextTick(() => $dispatch('modal-show', { name: @js(old('_modal')) }))"></div>@endif
    </main>
