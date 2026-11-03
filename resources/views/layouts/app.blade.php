@props(['title' => null])
<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main>
        @if (session('status'))
            <flux:callout variant="success" class="mb-6">{{ session('status') }}</flux:callout>
        @endif
        @if ($errors->any())
            <flux:callout variant="danger" class="mb-6" data-form-feedback>
                <ul class="list-disc ps-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </flux:callout>
        @endif
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
