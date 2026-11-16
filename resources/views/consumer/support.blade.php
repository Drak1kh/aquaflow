<x-layouts::app title="Help & Support">
<div class="client-page w-full min-w-0 space-y-6">
<x-page-header title="Support Requests & Inquiries" description="Contact your water utility team directly.">
    @can('tickets.create')
        <x-slot:actions>
            <flux:modal.trigger name="submit-support-ticket">
                <flux:button variant="primary" icon="plus-circle">Open New Ticket</flux:button>
            </flux:modal.trigger>
        </x-slot:actions>
    @endcan
</x-page-header>
@can('tickets.view')
<section class="space-y-4" aria-labelledby="support-history-heading">
<flux:heading id="support-history-heading" level="2">Your support history</flux:heading>
@forelse ($tickets as $ticket)
    <article class="space-y-4 rounded-xl border border-zinc-200 bg-white p-4 sm:p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <header class="space-y-2 border-b border-zinc-200 pb-4 dark:border-zinc-700">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-medium tabular-nums text-brand-ocean dark:text-sky-200">#{{ $ticket->id }}</span>
                    <flux:badge size="sm">{{ ucfirst($ticket->type) }}</flux:badge>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <x-ticket-status :status="$ticket->status" />
                    <x-ticket-close :ticket="$ticket" />
                </div>
            </div>
            <flux:heading level="3" size="lg"><flux:link :href="route('portal.tickets.show', $ticket)">{{ $ticket->subject }}</flux:link></flux:heading>
        </header>
        <x-ticket-conversation :ticket="$ticket" />
        <x-ticket-reply :ticket="$ticket" />
    </article>
@empty
    <div class="rounded-xl border border-dashed border-zinc-300 bg-brand-surface p-8 text-center dark:border-zinc-700 dark:bg-zinc-900">
        <flux:icon.chat-bubble-left-right class="mx-auto mb-3 size-8 text-zinc-400" />
        <flux:text>No support tickets yet. Open a new ticket when you need help with your account.</flux:text>
    </div>
@endforelse
{{ $tickets->links() }}
</section>
@endcan
<div class="grid items-start gap-4 lg:grid-cols-2">
<section class="space-y-4">
    <flux:heading level="2" size="lg">Contact your water provider</flux:heading>
    <dl class="grid gap-4 sm:grid-cols-2">
        @foreach (['Provider' => $provider->name, 'Contact number' => $provider->phone ?: 'Not provided', 'Email' => $provider->email ?: 'Not provided', 'Office address' => $provider->address ?: 'Contact your provider for office details.'] as $label => $value)
            <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ $label }}</dt><dd class="mt-1 font-medium">{{ $value }}</dd></div>
        @endforeach
    </dl>
</section>
<section class="space-y-4">
    <flux:heading level="2" size="lg">Using your water account</flux:heading>
    <ol class="list-decimal space-y-2 ps-5"><li>Open My Connection to check meter readings and consumption.</li><li>Open My Bills to review charges, due dates, and remaining balances. Use View / print bill to print or save a PDF.</li><li>Contact your provider for accepted payment methods. After paying, submit the reference and proof under Payments.</li><li>Wait for verification. Pending payments reduce your balance only after the provider confirms them.</li><li>Check Notifications for bill, payment, and request updates.</li></ol>
</section>
</div>
@can('tickets.create')
<x-form-modal name="submit-support-ticket" title="Submit a support concern" :action="route('portal.tickets.store')" submit-label="Submit concern">
    <div class="space-y-4">
    <flux:select name="type" label="Concern type" required>
        @foreach (['billing', 'payment', 'account', 'service', 'provider', 'other'] as $type)<option value="{{ $type }}" @selected(old('type') === $type)>{{ ucfirst($type) }}</option>@endforeach
    </flux:select>
    <flux:input name="subject" label="Subject" placeholder="Briefly describe your concern" :value="old('subject')" required />
    <flux:textarea name="description" label="Details" placeholder="Include the bill, payment reference, or service details we should review" required>{{ old('description') }}</flux:textarea>
    </div>
</x-form-modal>
@endcan
</div>
</x-layouts::app>
