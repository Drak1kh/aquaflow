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
    <flux:modal.trigger name="header-notifications">
        <flux:button class="min-h-11 min-w-11" variant="ghost" icon="bell" :aria-label="__('Notifications')" />
    </flux:modal.trigger>

    <flux:modal name="header-notifications" flyout position="right" class="w-screen max-w-lg space-y-6" aria-labelledby="notification-sheet-title">
        <div class="space-y-2 pe-8">
            <flux:heading id="notification-sheet-title" size="lg" level="2">{{ __('Notifications') }}</flux:heading>
            <flux:text>{{ trans_choice(':count unread notification|:count unread notifications', $this->unreadCount) }}</flux:text>
        </div>

        <div class="flex flex-wrap gap-3">
            <flux:button wire:click="toggleUnread" wire:loading.attr="disabled" size="sm">{{ $unreadOnly ? __('Show all') : __('Show unread') }}</flux:button>
            @if ($this->unreadCount)
                <flux:button wire:click="markAllAsRead" wire:loading.attr="disabled" size="sm">{{ __('Mark all as read') }}</flux:button>
            @endif
        </div>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
            @forelse ($this->notices as $notice)
                <article wire:key="header-notice-{{ $notice->id }}" class="space-y-3 py-5 first:pt-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:heading level="3" class="min-w-0 break-words">{{ $notice->title }}</flux:heading>
                        @if (! $notice->read_at)
                            <flux:badge color="blue" size="sm">{{ __('Unread') }}</flux:badge>
                        @endif
                    </div>
                    <flux:text class="break-words">{{ $notice->body }}</flux:text>
                    <time datetime="{{ $notice->created_at->toIso8601String() }}" class="block text-sm text-zinc-500 dark:text-zinc-400">{{ $notice->created_at->format('M d, Y H:i') }}</time>
                    <div class="flex flex-wrap gap-3">
                        <flux:button :href="$notice->url" size="sm" wire:navigate>{{ __('View details') }}</flux:button>
                        @if (! $notice->read_at)
                            <flux:button wire:click="markAsRead({{ $notice->id }})" wire:loading.attr="disabled" size="sm" variant="ghost">{{ __('Mark as read') }}</flux:button>
                        @endif
                    </div>
                </article>
            @empty
                <flux:text>{{ $unreadOnly ? __('No unread notifications.') : __('No notifications to show. New bills, payment confirmations, and request updates will appear here.') }}</flux:text>
            @endforelse
        </div>

        {{ $this->notices->links() }}
    </flux:modal>
</div>
