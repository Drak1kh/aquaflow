<x-layouts::app :title="$ticket->subject">
    <div class="grid w-full min-w-0 gap-7 lg:grid-cols-[minmax(0,1fr)_300px]">
        <main class="min-w-0 space-y-6">
            <x-page-header :title="$ticket->subject" :description="$ticket->waterProvider->name.' · '.$ticket->consumer->name.' · '.ucfirst($ticket->type)" :back-href="route('admin.tickets.index')" :back-label="__('Escalated disputes')" />

            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="font-medium">{{ __('Original concern') }}</div>
                <p class="mt-2 whitespace-pre-wrap text-sm">{{ $ticket->description }}</p>
            </div>

            <section class="space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <flux:heading size="lg">{{ __('Conversation and notes') }}</flux:heading>
                    @if ($ticket->status !== 'closed')
                        <flux:modal.trigger name="reply-to-ticket">
                            <flux:button variant="primary" icon="plus">{{ __('Add response') }}</flux:button>
                        </flux:modal.trigger>
                    @endif
                </div>
                @forelse ($ticket->messages as $message)
                    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex justify-between gap-3 text-sm text-zinc-500">
                            <span>{{ $message->internal ? __('Internal note') : __('Response') }}</span>
                            <time>{{ $message->created_at->format(app(\App\Actions\PlatformSettings::class)->get('date_format').' g:i A') }}</time>
                        </div>
                        <p class="mt-2 whitespace-pre-wrap">{{ $message->body }}</p>
                        @if ($message->attachment_path)
                            <flux:link :href="route('admin.tickets.attachment', $message)">{{ $message->attachment_name }}</flux:link>
                        @endif
                    </div>
                @empty
                    <flux:text>{{ __('No replies yet.') }}</flux:text>
                @endforelse
            </section>
        </main>

        <aside>
            <div class="space-y-4 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Review') }}</flux:heading>
                <dl class="space-y-3 text-sm">
                    <div><dt class="text-zinc-500">{{ __('Status') }}</dt><dd class="mt-1 font-medium">{{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</dd></div>
                    <div><dt class="text-zinc-500">{{ __('Assigned administrator') }}</dt><dd class="mt-1 font-medium">{{ $admins->firstWhere('id', $ticket->assigned_to)?->name ?? __('Unassigned') }}</dd></div>
                    @if ($ticket->resolution)
                        <div><dt class="text-zinc-500">{{ __('Resolution') }}</dt><dd class="mt-1 whitespace-pre-wrap">{{ $ticket->resolution }}</dd></div>
                    @endif
                </dl>
                <flux:modal.trigger name="edit-ticket-review">
                    <flux:button>{{ __('Edit review') }}</flux:button>
                </flux:modal.trigger>
            </div>
        </aside>
    </div>

    <x-form-modal name="edit-ticket-review" :title="__('Edit ticket review')" :action="route('admin.tickets.update', $ticket)" method="PUT" :submit-label="__('Save review')">
        @php($editingReview = old('_form') === 'edit-ticket-review')
        <div class="space-y-4">
            <flux:select name="status" :label="__('Status')">
                @foreach (['open', 'under_review', 'referred_to_provider', 'waiting_for_consumer', 'resolved', 'closed'] as $status)
                    <option value="{{ $status }}" @selected(($editingReview ? old('status') : $ticket->status) === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                @endforeach
            </flux:select>
            <flux:select name="assigned_to" :label="__('Assigned administrator')">
                <option value="">{{ __('Unassigned') }}</option>
                @foreach ($admins as $admin)
                    <option value="{{ $admin->id }}" @selected(($editingReview ? old('assigned_to') : $ticket->assigned_to) == $admin->id)>{{ $admin->name }}</option>
                @endforeach
            </flux:select>
            <flux:textarea name="resolution" :label="__('Resolution')">{{ $editingReview ? old('resolution') : $ticket->resolution }}</flux:textarea>
        </div>
    </x-form-modal>

    @if ($ticket->status !== 'closed')
        <x-form-modal name="reply-to-ticket" :title="__('Add response')" :action="route('admin.tickets.reply', $ticket)" :submit-label="__('Post response')" :upload="true">
            <div class="space-y-4">
                <flux:textarea name="body" :label="__('Message')" required>{{ old('_form') === 'reply-to-ticket' ? old('body') : '' }}</flux:textarea>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="internal" value="1" @checked(old('_form') === 'reply-to-ticket' && old('internal')) class="size-4 accent-[#0869CF]">
                    {{ __('Internal note (hidden from consumer)') }}
                </label>
                <flux:input name="attachment" type="file" :label="__('Attachment (PDF or image, up to 5 MB)')" />
            </div>
        </x-form-modal>
    @endif
</x-layouts::app>
