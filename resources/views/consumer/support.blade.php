<x-layouts::app title="Help &amp; Support">
<div class="client-page w-full min-w-0 space-y-6">
<x-page-header title="Help &amp; Support" :description="$consumer->account_number">
    @can('tickets.create')
        <x-slot:actions>
            <flux:modal.trigger name="submit-support-ticket">
                <flux:button variant="primary">Submit concern</flux:button>
            </flux:modal.trigger>
        </x-slot:actions>
    @endcan
</x-page-header>
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
@can('tickets.view')
<flux:heading level="2">Your support history</flux:heading>
@forelse ($tickets as $ticket)
    <flux:card><flux:link :href="route('portal.tickets.show', $ticket)">{{ $ticket->subject }}</flux:link><flux:text>{{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</flux:text></flux:card>
@empty <flux:text>No support tickets yet.</flux:text> @endforelse
{{ $tickets->links() }}
@endcan
</div>
</x-layouts::app>
