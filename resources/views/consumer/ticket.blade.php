<x-layouts::app :title="$ticket->subject">
    <div class="mx-auto max-w-3xl space-y-6">
        <x-page-header :title="$ticket->subject" :description="ucfirst(str_replace('_', ' ', $ticket->status))" :back-href="route('dashboard.consumer')" :back-label="__('My account')" />
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900"><p class="whitespace-pre-wrap">{{ $ticket->description }}</p></div>

        @foreach ($ticket->messages as $message)
            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:text class="text-sm">{{ $message->created_at->format(app(\App\Actions\PlatformSettings::class)->get('date_format').' g:i A') }}</flux:text>
                <p class="mt-2 whitespace-pre-wrap">{{ $message->body }}</p>
                @if ($message->attachment_path)
                    <flux:link :href="route('portal.tickets.attachment', $message)">{{ $message->attachment_name }}</flux:link>
                @endif
            </div>
        @endforeach

        @if ($ticket->status !== 'closed')
            @can('permission', 'tickets.respond')
                <div class="flex justify-end">
                    <flux:modal.trigger name="reply-to-ticket">
                        <flux:button variant="primary">{{ __('Reply') }}</flux:button>
                    </flux:modal.trigger>
                </div>
                <x-form-modal name="reply-to-ticket" :title="__('Reply to ticket')" :action="route('portal.tickets.reply', $ticket)" :submit-label="__('Send reply')" :upload="true">
                    <div class="space-y-4">
                        <flux:textarea name="body" :label="__('Message')" required>{{ old('_form') === 'reply-to-ticket' ? old('body') : '' }}</flux:textarea>
                        <flux:input name="attachment" type="file" :label="__('Attachment (PDF or image, up to 5 MB)')" />
                    </div>
                </x-form-modal>
            @endcan
        @endif
    </div>
</x-layouts::app>
