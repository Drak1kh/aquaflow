@props(['ticket', 'provider' => false, 'admin' => false])

@php($dateFormat = app(\App\Actions\PlatformSettings::class)->get('date_format').' g:i A')
@php($staffView = $provider || $admin)

<div class="space-y-3" aria-label="{{ __('Ticket conversation') }}">
    <div @class([
        'rounded-lg border p-4 text-sm',
        'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/60' => $staffView,
        'ms-auto w-full border-cyan-200 bg-cyan-50 sm:w-[85%] dark:border-cyan-800 dark:bg-cyan-950/40' => !$staffView,
    ])>
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-xs">
            <p class="font-semibold text-brand-ink dark:text-zinc-100">{{ $ticket->consumer->name }} <span class="font-normal text-zinc-500 dark:text-zinc-400">{{ $staffView ? __('(Customer)') : __('(You)') }}</span></p>
            <time datetime="{{ $ticket->created_at->toIso8601String() }}" class="text-zinc-500 dark:text-zinc-400">{{ $ticket->created_at->format($dateFormat) }}</time>
        </div>
        <p class="mt-2 whitespace-pre-wrap leading-relaxed text-brand-ink dark:text-zinc-200">{{ $ticket->description }}</p>
    </div>

    @foreach ($ticket->messages as $message)
        @continue($message->internal && !$admin)
        @php($fromConsumer = $message->author?->consumer_id === $ticket->consumer_id)
        <div @class([
            'rounded-lg border p-4 text-sm',
            'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/60' => !$message->internal && ($staffView ? $fromConsumer : !$fromConsumer),
            'border-cyan-200 bg-cyan-50 dark:border-cyan-800 dark:bg-cyan-950/40' => !$message->internal && ($staffView ? !$fromConsumer : $fromConsumer),
            'border-amber-300 bg-amber-50 dark:border-amber-700 dark:bg-amber-950/40' => $message->internal,
            'w-full sm:w-[85%]' => !$staffView,
            'ms-auto' => !$staffView && $fromConsumer,
        ])>
            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-xs">
                <p class="font-semibold text-brand-ink dark:text-zinc-100">{{ $message->author?->name ?? __('Utility staff') }} <span class="font-normal text-zinc-500 dark:text-zinc-400">{{ $fromConsumer ? ($staffView ? __('(Customer)') : __('(You)')) : __('(Utility staff)') }}</span></p>
                <time datetime="{{ $message->created_at->toIso8601String() }}" class="text-zinc-500 dark:text-zinc-400">{{ $message->created_at->format($dateFormat) }}</time>
            </div>
            @if ($message->internal)
                <flux:badge color="amber" size="sm" icon="lock-closed" class="mt-2">{{ __('Internal staff note · Hidden from customer') }}</flux:badge>
            @endif
            <p class="mt-2 whitespace-pre-wrap leading-relaxed text-brand-ink dark:text-zinc-200">{{ $message->body }}</p>
            @if ($message->attachment_path)
                <flux:link :href="route($admin ? 'admin.tickets.attachment' : ($provider ? 'provider.tickets.attachment' : 'portal.tickets.attachment'), $message)" class="mt-3 inline-flex items-center gap-1.5 text-sm underline">
                    <flux:icon.paper-clip class="size-4" />{{ $message->attachment_name ?: __('Download attachment') }}
                </flux:link>
            @endif
        </div>
    @endforeach
</div>
