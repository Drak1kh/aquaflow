@props(['title' => null])
<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main>
        <x-session-feedback />
        {{ $slot }}
    </flux:main>
    <x-document-preview />
</x-layouts::app.sidebar>
