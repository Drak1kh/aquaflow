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

<flux:modal x-on:keydown.tab="
    const items = [...$el.querySelectorAll('button:not([disabled]), a[href], input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]')].filter(item => item.tabIndex >= 0 && item.getClientRects().length);
    const first = items[0];
    const last = items[items.length - 1];
    if ($event.shiftKey && document.activeElement === first) { $event.preventDefault(); last?.focus(); }
    if (!$event.shiftKey && document.activeElement === last) { $event.preventDefault(); first?.focus(); }
" :name="$name" :show="($modalErrors->any() && old('_form') === $formMarker) || ($open && !$modalErrors->any())" class="w-full max-w-2xl">
    <form x-init="$nextTick(() => { const dialog = $el.closest('dialog'); const focusFirst = () => $el.querySelector('input:not([type=hidden]), select, textarea')?.focus(); new MutationObserver(() => { if (dialog.open) $nextTick(focusFirst); }).observe(dialog, { attributes: true, attributeFilter: ['open'] }); if (dialog.open) focusFirst(); })" method="POST" action="{{ $action }}" @if ($upload) enctype="multipart/form-data" @endif class="space-y-6">
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
</flux:modal>
