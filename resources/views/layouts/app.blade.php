@props(['title' => null])
<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main>
        <x-session-feedback />
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
