<x-layouts::app title="Help &amp; Support">
<div class="w-full min-w-0 space-y-6">
<x-page-header title="Help &amp; Support" :description="$consumer->account_number" />
@can('tickets.create')
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
<flux:heading level="2">Your support history</flux:heading>
@forelse ($tickets as $ticket)
    <flux:card><flux:link :href="route('portal.tickets.show', $ticket)">{{ $ticket->subject }}</flux:link><flux:text>{{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</flux:text></flux:card>
@empty <flux:text>No support tickets yet.</flux:text> @endforelse
{{ $tickets->links() }}
</div>
</x-layouts::app>
