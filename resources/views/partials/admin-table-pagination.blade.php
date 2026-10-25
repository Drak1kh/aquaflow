<nav aria-label="Table pagination" class="mt-5 flex flex-wrap items-center justify-between gap-4 border-t border-[#BDECF8] pt-5 dark:border-slate-700">
    <p class="text-sm text-[#365A87] dark:text-slate-300" aria-live="polite">
        @if ($paginator->total() > 0)
            Showing <span class="tabular-nums">{{ $paginator->firstItem() }}</span> to <span class="tabular-nums">{{ $paginator->lastItem() }}</span> of <span class="tabular-nums">{{ $paginator->total() }}</span> results
        @else
            Showing 0 results
        @endif
    </p>

    <flux:button.group>
        <flux:button wire:click="previousPage" wire:loading.attr="disabled" :disabled="$paginator->onFirstPage()" icon="chevron-left" size="sm" square aria-label="Previous page" />
        <flux:button wire:click="nextPage" wire:loading.attr="disabled" :disabled="! $paginator->hasMorePages()" icon="chevron-right" size="sm" square aria-label="Next page" />
    </flux:button.group>
</nav>
