@props(['id', 'title', 'role'])
<section id="{{ $id }}" class="docs-topic" data-doc-topic data-doc-role="{{ $role }}">
    <h3 tabindex="-1">{{ $title }}</h3>
    {{ $slot }}
</section>
