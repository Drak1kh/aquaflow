@props([
    'name',
    'title',
    'action',
    'submitLabel',
    'description' => null,
    'method' => 'POST',
    'marker' => null,
    'upload' => false,
    'variant' => 'primary',
])

@php
    $formMarker = $marker ?? $name;
    $modalErrors = $errors ?? new \Illuminate\Support\ViewErrorBag;
@endphp

<flux:modal :name="$name" :show="$modalErrors->any() && old('_form') === $formMarker" class="w-full max-w-2xl">
    <form method="POST" action="{{ $action }}" @if ($upload) enctype="multipart/form-data" @endif class="space-y-6">
        @csrf
        @if (strtoupper($method) !== 'POST')
            @method($method)
        @endif
        <input type="hidden" name="_form" value="{{ $formMarker }}">

        <div>
            <flux:heading size="lg">{{ $title }}</flux:heading>
            @if ($description)
                <flux:text class="mt-2">{{ $description }}</flux:text>
            @endif
        </div>

        @if ($modalErrors->any() && old('_form') === $formMarker)
            <flux:callout variant="danger">
                <ul class="list-disc ps-5">
                    @foreach ($modalErrors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </flux:callout>
        @endif

        {{ $slot }}

        <div class="flex flex-wrap justify-end gap-2">
            <flux:modal.close>
                <flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" :variant="$variant">{{ $submitLabel }}</flux:button>
        </div>
    </form>
</flux:modal>
