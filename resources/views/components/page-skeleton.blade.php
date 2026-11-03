@props(['variant' => 'page'])

<flux:skeleton.group animate="pulse" {{ $attributes->class('w-full min-w-0 space-y-6') }} aria-hidden="true">
    <div class="space-y-3">
        <flux:skeleton class="h-8 w-2/3 max-w-72 rounded-md" />
        <flux:skeleton.line class="w-4/5 max-w-lg" />
    </div>

    @if ($variant === 'dashboard')
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach (range(1, 4) as $item)
                <div class="space-y-3 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                    <flux:skeleton.line class="w-2/3" />
                    <flux:skeleton class="h-8 w-24 rounded-md" />
                    <flux:skeleton.line />
                </div>
            @endforeach
        </div>
        <flux:skeleton class="h-64 w-full rounded-xl" />
    @elseif ($variant === 'form')
        @foreach (range(1, 3) as $field)
            <div class="space-y-2">
                <flux:skeleton.line class="w-24" />
                <flux:skeleton class="h-10 w-full rounded-lg" />
            </div>
        @endforeach
        <flux:skeleton class="h-10 w-32 rounded-lg" />
    @else
        @if ($variant === 'document')
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="space-y-3"><flux:skeleton.line class="w-2/3" /><flux:skeleton.line /><flux:skeleton.line class="w-4/5" /></div>
                <div class="space-y-3"><flux:skeleton.line /><flux:skeleton.line class="w-4/5" /><flux:skeleton.line class="w-2/3" /></div>
            </div>
        @else
            <div class="flex flex-wrap gap-3"><flux:skeleton class="h-10 w-48 rounded-lg" /><flux:skeleton class="h-10 w-28 rounded-lg" /></div>
        @endif
        <div class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
            @foreach (range(1, 5) as $row)
                <div class="flex items-center justify-between gap-5 p-5">
                    <div class="flex-1 space-y-2"><flux:skeleton.line class="w-2/3" /><flux:skeleton.line class="w-4/5" /></div>
                    <flux:skeleton class="h-6 w-16 rounded-md" />
                </div>
            @endforeach
        </div>
    @endif
</flux:skeleton.group>
