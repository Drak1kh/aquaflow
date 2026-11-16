@props(['ticket', 'provider' => false, 'admin' => false])

@if ($ticket->status !== 'closed')
    @can('tickets.respond')
        @php
            $formMarker = 'reply-to-ticket-'.$ticket->id;
            $replying = old('_form') === $formMarker;
            $replyErrors = $errors->getBag($formMarker);
        @endphp
        <form method="POST" action="{{ route($admin ? 'admin.tickets.reply' : ($provider ? 'provider.tickets.reply' : 'portal.tickets.reply'), $ticket) }}" enctype="multipart/form-data" class="space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
            @csrf
            <input type="hidden" name="_form" value="{{ $formMarker }}">
            @if ($admin)
                <flux:checkbox name="internal" value="1" :label="__('Internal staff note (hidden from customer)')" :checked="$replying && old('internal')" :invalid="$replyErrors->has('internal')" error:name="internal" :error:bag="$formMarker" />
            @endif
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="min-w-0 flex-1">
                    <flux:textarea name="body" :label="$provider ? __('Compose response') : __('Reply to utility staff')" :placeholder="$provider ? __('Write a response to the customer…') : __('Type a response to the utility staff…')" :invalid="$replyErrors->has('body')" error:name="body" :error:bag="$formMarker" rows="2" maxlength="10000" required>{{ $replying ? old('body') : '' }}</flux:textarea>
                </div>
                <flux:button type="submit" variant="primary" icon="paper-airplane">{{ $admin ? __('Send response') : ($provider ? __('Send customer reply') : __('Reply')) }}</flux:button>
            </div>
            <details @if ($replyErrors->has('attachment')) open @endif class="text-sm text-zinc-600 dark:text-zinc-300">
                <summary class="w-fit cursor-pointer rounded py-1">{{ __('Add attachment') }}</summary>
                <div class="mt-2">
                    <flux:input name="attachment" type="file" :label="__('Attachment (PDF or image, up to 5 MB)')" :invalid="$replyErrors->has('attachment')" error:name="attachment" :error:bag="$formMarker" accept=".pdf,.jpg,.jpeg,.png" :description="__('Reattach the file if validation fails.')" />
                </div>
            </details>
        </form>
    @endcan
@endif
