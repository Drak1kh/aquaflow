@php
    $role = auth()->user()->role;
    $description = match ($role) {
        \App\UserRole::SuperAdmin => __('Your platform overview and administration tools will appear here as they are added.'),
        \App\UserRole::WaterProvider => __('Your provider operations and consumer records will appear here as they are added.'),
        \App\UserRole::Consumer => __('Your water account, bills, and service information will appear here as they are added.'),
    };
@endphp

<x-layouts::app :title="__(':role Dashboard', ['role' => $role->label()])">
    <div class="max-w-3xl rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:badge color="blue">{{ $role->label() }}</flux:badge>
        <flux:heading size="xl" class="mt-4">{{ __('Welcome, :name', ['name' => auth()->user()->name]) }}</flux:heading>
        <flux:text class="mt-2">{{ $description }}</flux:text>
    </div>
</x-layouts::app>
