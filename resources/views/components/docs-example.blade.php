@props(['title'])
<aside class="docs-example" aria-label="{{ __('Example: :title', ['title' => $title]) }}">
    <h4>{{ $title }}</h4>
    <p class="docs-example-label">{{ __('Fictional example') }}</p>
    {{ $slot }}
</aside>
