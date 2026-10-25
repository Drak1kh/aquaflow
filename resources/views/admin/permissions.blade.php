<x-layouts::app :title="__('Roles and permissions')">
    <div class="flex w-full min-w-0 flex-col gap-7">
        <x-page-header :title="__('Roles and permissions')" :description="__('Choose the actions each role can perform. Changes take effect on the next request.')" />

        <div class="rounded-xl border border-[#BDECF8] bg-[#F7FCFE] p-5 text-[#062E68] dark:border-zinc-700 dark:bg-zinc-900 dark:text-white">
            <div class="font-semibold">{{ __('Super Admin') }}</div>
            <p class="mt-1 text-sm">{{ __('Full platform access is always enabled to prevent administrative lockout.') }}</p>
        </div>

        @foreach ([\App\UserRole::WaterProvider, \App\UserRole::Consumer] as $role)
            @php
                $availablePermissions = $defaults[$role->value];
                $roleOverrides = $overrides->get($role->value);
                $enabledCount = collect($availablePermissions)->filter(fn ($permission) => $roleOverrides?->firstWhere('permission', $permission)?->allowed ?? true)->count();
                $editingPermissions = old('_form') === 'edit-permissions-'.$role->value;
            @endphp
            <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <div>
                    <flux:heading size="lg">{{ $role->label() }}</flux:heading>
                    <flux:text class="mt-1">{{ __(':enabled of :total actions enabled', ['enabled' => $enabledCount, 'total' => count($availablePermissions)]) }}</flux:text>
                </div>
                <flux:modal.trigger :name="'edit-permissions-'.$role->value">
                    <flux:button icon="pencil-square">{{ __('Edit permissions') }}</flux:button>
                </flux:modal.trigger>
            </div>

            <x-form-modal :name="'edit-permissions-'.$role->value" :title="__('Edit :role permissions', ['role' => $role->label()])" :action="route('admin.permissions.update')" method="PUT" :submit-label="__('Save permissions')">
                <input type="hidden" name="role" value="{{ $role->value }}">
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($availablePermissions as $permission)
                        @php($override = $roleOverrides?->firstWhere('permission', $permission))
                        <label class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 hover:bg-[#F7FCFE] dark:hover:bg-zinc-800">
                            <input type="checkbox" name="permissions[]" value="{{ $permission }}" @checked($editingPermissions ? in_array($permission, old('permissions', []), true) : ($override?->allowed ?? true)) class="size-4 accent-[#0869CF]">
                            <span class="text-sm">{{ $labels[$permission] }}</span>
                        </label>
                    @endforeach
                </div>
            </x-form-modal>
        @endforeach
    </div>
</x-layouts::app>
