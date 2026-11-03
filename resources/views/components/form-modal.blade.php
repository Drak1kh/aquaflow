@props([
    'open' => false,
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

<x-accessible-modal :name="$name" :show="($modalErrors->any() && old('_form') === $formMarker) || ($open && !$modalErrors->any())" class="w-full max-w-2xl">
    <form method="POST" action="{{ $action }}" @if ($upload) enctype="multipart/form-data" @endif class="space-y-6">
        @csrf
        @if (strtoupper($method) !== 'POST')
            @method($method)
        @endif
        <input type="hidden" name="_form" value="{{ $formMarker }}">

        <div>
            <flux:heading level="2" size="lg">{{ $title }}</flux:heading>
            @if ($description)
                <flux:text class="mt-2">{{ $description }}</flux:text>
            @endif
        </div>

        {{ $slot }}

        <div class="flex flex-wrap justify-end gap-2">
            <flux:modal.close>
                <flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" :variant="$variant">{{ $submitLabel }}</flux:button>
        </div>
    </form>
</x-accessible-modal>
