<x-layouts::app title="Notifications">
<div class="client-page w-full min-w-0 space-y-6">
<x-page-header title="Notifications" :description="$unreadCount.' unread notification(s)'" />
<div class="flex flex-wrap gap-3">
<flux:button :href="route('portal.section', ['section' => 'notifications', 'unread' => request('unread') ? 0 : 1])">{{ request('unread') ? 'Show all' : 'Show unread' }}</flux:button>
@if($unreadCount)<form method="POST" action="{{ route('portal.notifications.read-all') }}">@csrf @method('PATCH')<flux:button type="submit">Mark all as read</flux:button></form>@endif
</div>
@forelse($notices as $notice)
<article class="space-y-3 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
    <flux:heading level="2">{{ $notice->title }} @if(!$notice->read_at)<flux:badge color="blue">Unread</flux:badge>@endif</flux:heading>
    <flux:text>{{ $notice->body }}</flux:text><time class="text-sm text-zinc-500 dark:text-zinc-400">{{ $notice->created_at->format('M d, Y H:i') }}</time>
    <div class="flex flex-wrap gap-3"><flux:button :href="$notice->url">View details</flux:button>
    @if(!$notice->read_at)<form method="POST" action="{{ route('portal.notifications.read', $notice) }}">@csrf @method('PATCH')<flux:button type="submit">Mark as read</flux:button></form>@endif</div>
</article>
@empty <flux:text>No notifications to show. New bills, payment confirmations, and request updates will appear here.</flux:text> @endforelse
{{ $notices->links() }}
</div>
</x-layouts::app>
