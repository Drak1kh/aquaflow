@props(['title' => null])
<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main>
        <x-page-loading :variant="match (true) {
            request()->routeIs('dashboard', 'dashboard.*') => 'dashboard',
            request()->routeIs('*.edit', 'portal.settings', 'admin.settings.index', 'admin.permissions.index') => 'form',
            default => 'page',
        }">
            <x-session-feedback />
            {{ $slot }}
        </x-page-loading>
    </flux:main>
    <x-document-preview />
</x-layouts::app.sidebar>
