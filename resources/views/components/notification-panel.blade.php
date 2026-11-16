@props(['unreadCount' => 0])

<flux:dropdown position="bottom" align="end">
    <flux:button class="min-h-11 min-w-11" variant="ghost" icon="bell" icon:variant="outline" :aria-label="__('Notifications')" aria-haspopup="dialog" aria-controls="header-notifications" />

    <div id="header-notifications" popover="manual" role="dialog" aria-labelledby="notification-sheet-title" wire:ignore.self class="m-0 max-h-[min(40rem,calc(100dvh-6rem))] w-[420px] max-w-[calc(100vw-1.5rem)] flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white p-0 font-sans shadow-xl [&:popover-open]:flex dark:border-zinc-700 dark:bg-zinc-900">
        <div class="shrink-0 border-b border-zinc-100 px-5 pt-5 pb-3 dark:border-zinc-800">
            <flux:heading id="notification-sheet-title" level="2" class="text-base! font-semibold!">{{ __('Notifications') }}</flux:heading>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ trans_choice(':count unread notification|:count unread notifications', $unreadCount) }}</p>

            @isset($filters)
                {{ $filters }}
            @endisset
        </div>

        <div class="min-h-0 overflow-y-auto overscroll-contain">
            @if ($slot->isNotEmpty())
                {{ $slot }}
            @else
                <div class="px-6 py-10 text-center">
                    <div class="mx-auto mb-3 flex size-11 items-center justify-center rounded-full bg-zinc-100 text-zinc-400 dark:bg-zinc-800">
                        <flux:icon.bell variant="outline" class="size-5" />
                    </div>
                    <flux:text class="text-sm!">{{ __('No notifications yet') }}</flux:text>
                </div>
            @endif
        </div>
    </div>
</flux:dropdown>
