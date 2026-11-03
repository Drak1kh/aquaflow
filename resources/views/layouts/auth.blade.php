<x-layouts::auth.split :title="$title ?? null">
    <x-page-loading variant="form">{{ $slot }}</x-page-loading>
</x-layouts::auth.split>
