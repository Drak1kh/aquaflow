<x-layouts::app title="Help &amp; Support">
<div class="client-page w-full min-w-0 space-y-6">
<x-page-header title="Help &amp; Support" :description="$consumer->account_number" />
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
<section class="space-y-3">
    <flux:heading level="2" size="lg">Frequently asked questions</flux:heading>
    @foreach ([
        'How is my consumption calculated?' => 'Consumption is the current meter reading minus the previous reading. Bills preserve the readings and rate used when they were generated.',
        'How are water charges calculated?' => 'The bill includes the configured base charge plus consumption multiplied by the rate per cubic meter. Approved adjustments or applicable penalties are shown on the bill.',
        'Why is my payment still pending?' => 'Your provider must verify submitted payment details and proof before applying the payment. If rejected, review the reason under Payments and contact your provider.',
        'Can I make a partial payment?' => 'Yes. Submit an amount up to the remaining bill balance. A verified partial payment reduces the balance; the bill is paid only when the remaining balance reaches zero.',
        'Can I change my meter or registered address?' => 'Official account, meter, and connection records are maintained by your provider. Submit a support concern to request a correction.',
        'Does payment automatically reconnect service?' => 'No. Your provider reviews the account and records a reconnection request and completed restoration separately.',
    ] as $question => $answer)
        <details class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700"><summary class="cursor-pointer font-medium">{{ $question }}</summary><p class="mt-3 text-sm leading-relaxed text-zinc-600 dark:text-zinc-300">{{ $answer }}</p></details>
    @endforeach
</section>
@can('tickets.create')
<flux:heading level="2" size="lg">Submit a support concern</flux:heading>
<form method="POST" action="{{ route('portal.tickets.store') }}" class="space-y-4">
    @csrf
    <flux:select name="type" label="Concern type" required>
        @foreach (['billing', 'payment', 'account', 'service', 'provider', 'other'] as $type)<option value="{{ $type }}" @selected(old('type') === $type)>{{ ucfirst($type) }}</option>@endforeach
    </flux:select>
    <flux:input name="subject" label="Subject" placeholder="Briefly describe your concern" :value="old('subject')" required />
    <flux:textarea name="description" label="Details" placeholder="Include the bill, payment reference, or service details we should review" required>{{ old('description') }}</flux:textarea>
    <flux:button type="submit" variant="primary">Submit concern</flux:button>
</form>
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
