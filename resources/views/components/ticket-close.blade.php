@props(['ticket', 'provider' => false])

@if ($ticket->status !== 'closed')
    @can('tickets.respond')
        @php
            $closeMarker = 'close-ticket-'.$ticket->id;
            $closing = old('_form') === $closeMarker;
            $closeErrors = $errors->getBag($closeMarker);
        @endphp
        <flux:modal.trigger :name="$closeMarker">
            <flux:button size="sm" icon="check-circle">{{ __('Close ticket') }}</flux:button>
        </flux:modal.trigger>
        <x-form-modal :name="$closeMarker" :title="__('Close ticket')" :description="__('Add a short closure reason. Closed tickets no longer accept replies.')" :action="route($provider ? 'provider.tickets.close' : 'portal.tickets.close', $ticket)" :submit-label="__('Close ticket')" :open="$closing && $closeErrors->any()">
            <flux:textarea name="resolution" :label="__('Closure reason')" placeholder="Describe why this ticket is ready to close" maxlength="5000" :invalid="$closeErrors->has('resolution')" error:name="resolution" :error:bag="$closeMarker" required>{{ $closing ? old('resolution') : '' }}</flux:textarea>
        </x-form-modal>
    @endcan
@endif
