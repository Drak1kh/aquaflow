@props(['variant' => 'page'])

<div {{ $attributes->class('w-full min-w-0') }} data-page-shell aria-busy="false">
    <div data-page-loading hidden aria-hidden="true" role="status">
        <span class="sr-only">{{ __('Loading page…') }}</span>
        <x-page-skeleton :variant="$variant" />
    </div>
    <div data-page-content>{{ $slot }}</div>
</div>
