@props([
    'title',
    'description' => null,
    'backHref' => null,
    'backLabel' => null,
])

<header {{ $attributes->class(['w-full min-w-0 space-y-5']) }}>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0 space-y-2">
            @if ($backHref)
                <flux:link :href="$backHref">{{ $backLabel ?? __('Back') }}</flux:link>
            @endif

            <flux:heading size="xl" level="1" class="break-words">{{ $title }}</flux:heading>

            @if ($description)
                <flux:text class="max-w-3xl break-words">{{ $description }}</flux:text>
            @endif
        </div>

        @isset($actions)
            <div class="flex flex-wrap items-center gap-2">
                {{ $actions }}
            </div>
        @endisset
    </div>

    <flux:separator />
</header>
