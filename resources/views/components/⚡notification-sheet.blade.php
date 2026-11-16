<?php

use App\Models\ClientNotice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination, WithoutUrlPagination;

    public bool $unreadOnly = false;

    public function boot(): void
    {
        Gate::authorize('access-consumer');
        Gate::authorize('notifications.view');
    }

    /** @return Builder<ClientNotice> */
    protected function inbox(): Builder
    {
        return ClientNotice::where('user_id', auth()->id())
            ->where('consumer_id', auth()->user()->consumer_id);
    }

    #[Computed]
    public function notices(): LengthAwarePaginator
    {
        return $this->inbox()
            ->when($this->unreadOnly, fn (Builder $query) => $query->whereNull('read_at'))
            ->latest('id')->paginate(10, pageName: 'notificationsPage');
    }

    #[Computed]
    public function unreadCount(): int
    {
        return $this->inbox()->whereNull('read_at')->count();
    }

    public function toggleUnread(): void
    {
        $this->unreadOnly = ! $this->unreadOnly;
        $this->resetPage('notificationsPage');
    }

    public function markAsRead(int $noticeId): void
    {
        $notice = $this->inbox()->findOrFail($noticeId);
        if ($notice->read_at === null) {
            $notice->update(['read_at' => now()]);
        }
        $this->resetPage('notificationsPage');
    }

    public function markAllAsRead(): void
    {
        $this->inbox()->whereNull('read_at')->update(['read_at' => now()]);
        $this->resetPage('notificationsPage');
    }
}; ?>

<div>
    <x-notification-panel :unread-count="$this->unreadCount">
        <x-slot:filters>
            <div class="mt-3 flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                <div class="flex gap-1" role="group" aria-label="{{ __('Filter notifications') }}">
                    <button type="button" wire:click="toggleUnread" wire:loading.attr="disabled" @disabled(! $unreadOnly) aria-pressed="{{ $unreadOnly ? 'false' : 'true' }}" @class(['min-h-11 rounded-lg px-4 text-xs font-semibold outline-offset-2 focus-visible:outline-2 focus-visible:outline-brand-primary', 'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white' => ! $unreadOnly, 'cursor-pointer text-zinc-500 hover:bg-zinc-50 dark:text-zinc-400 dark:hover:bg-zinc-800' => $unreadOnly])>{{ __('All') }}</button>
                    <button type="button" wire:click="toggleUnread" wire:loading.attr="disabled" @disabled($unreadOnly) aria-pressed="{{ $unreadOnly ? 'true' : 'false' }}" @class(['min-h-11 rounded-lg px-4 text-xs font-semibold outline-offset-2 focus-visible:outline-2 focus-visible:outline-brand-primary', 'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white' => $unreadOnly, 'cursor-pointer text-zinc-500 hover:bg-zinc-50 dark:text-zinc-400 dark:hover:bg-zinc-800' => ! $unreadOnly])>{{ __('Unread') }} <span class="ms-1 tabular-nums">({{ $this->unreadCount }})</span></button>
                </div>
                @if ($this->unreadCount)
                    <flux:button wire:click="markAllAsRead" wire:loading.attr="disabled" size="sm" variant="ghost" class="min-h-11 px-0! text-xs! text-brand-primary! dark:text-brand-mist!">{{ __('Mark all as read') }}</flux:button>
                @endif
            </div>
        </x-slot:filters>
        <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
            @forelse ($this->notices as $notice)
                <article wire:key="header-notice-{{ $notice->id }}" class="flex gap-3 px-5 py-4">
                    <div @class(['flex size-9 shrink-0 items-center justify-center rounded-full', 'bg-brand-primary/10 text-brand-primary dark:bg-brand-primary/20 dark:text-brand-mist' => ! $notice->read_at, 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400' => $notice->read_at]) aria-hidden="true">
                        <flux:icon.bell variant="outline" class="size-4" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-3">
                            <flux:heading level="3" class="min-w-0 [overflow-wrap:anywhere] text-sm! leading-5!">{{ $notice->title }}</flux:heading>
                            <div class="flex shrink-0 flex-col items-end gap-2 pt-0.5">
                                <time datetime="{{ $notice->created_at->toIso8601String() }}" title="{{ $notice->created_at->format('M d, Y H:i') }}" class="text-xs text-zinc-500 dark:text-zinc-400">{{ $notice->created_at->format('M j') }}</time>
                                @if (! $notice->read_at)
                                    <span class="size-1.5 rounded-full bg-brand-primary dark:bg-brand-aqua"><span class="sr-only">{{ __('Unread') }}</span></span>
                                @endif
                            </div>
                        </div>
                        <p class="mt-1 [overflow-wrap:anywhere] text-[13px] leading-5 text-zinc-600 dark:text-zinc-300">{{ $notice->body }}</p>
                        <div class="-ms-2 mt-1 flex flex-wrap gap-x-2">
                            <flux:button :href="$notice->url" size="sm" variant="ghost" class="min-h-11 text-xs! text-brand-primary! dark:text-brand-mist!">{{ __('View details') }}</flux:button>
                            @if (! $notice->read_at)
                                <flux:button wire:click="markAsRead({{ $notice->id }})" wire:loading.attr="disabled" size="sm" variant="ghost" class="min-h-11 text-xs!">{{ __('Mark as read') }}</flux:button>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="px-6 py-10 text-center">
                    <div class="mx-auto mb-3 flex size-11 items-center justify-center rounded-full bg-zinc-100 text-zinc-400 dark:bg-zinc-800">
                        <flux:icon.bell variant="outline" class="size-5" />
                    </div>
                    <flux:text class="text-sm!">{{ $unreadOnly ? __('No unread notifications.') : __('No notifications to show. New bills, payment confirmations, and request updates will appear here.') }}</flux:text>
                </div>
            @endforelse
        </div>

        @if ($this->notices->hasPages())
            <div class="border-t border-zinc-100 px-5 py-3 dark:border-zinc-800">
                <flux:pagination :paginator="$this->notices" class="border-0! pt-0! [&_button]:min-h-11 [&_button]:min-w-11" />
            </div>
        @endif
    </x-notification-panel>
</div>
