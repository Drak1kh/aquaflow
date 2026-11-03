@props([
    'text',
    'variant' => 'success',
    'duration' => 5000,
    'link' => null,
])

@php
    $toast = ['text' => $text, 'variant' => $variant, 'duration' => $duration, 'link' => $link];
@endphp

<div
    {{ $attributes->merge(['hidden' => true]) }}
    x-data="{ toast: @js($toast) }"
    x-init="$nextTick(() => $flux.toast(toast))"
></div>
