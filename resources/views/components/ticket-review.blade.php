@props(['ticket', 'admins'])

@php
    $reviewMarker = 'edit-ticket-review-'.$ticket->id;
    $editingReview = old('_form') === $reviewMarker;
    $reviewErrors = $errors->getBag($reviewMarker);
@endphp

<flux:modal.trigger :name="$reviewMarker">
    <flux:button size="sm" class="aquaflow-edit-action" icon="pencil-square">{{ __('Edit review') }}</flux:button>
</flux:modal.trigger>
<x-form-modal :name="$reviewMarker" :title="__('Edit ticket review')" :action="route('admin.tickets.update', $ticket)" method="PUT" :submit-label="__('Save review')" :open="$editingReview && $reviewErrors->any()">
    <div class="space-y-4">
        <flux:select name="status" :label="__('Status')" :invalid="$reviewErrors->has('status')" error:name="status" :error:bag="$reviewMarker">
            @foreach (['open', 'under_review', 'referred_to_provider', 'waiting_for_consumer', 'resolved', 'closed'] as $status)
                <option value="{{ $status }}" @selected(($editingReview ? old('status') : $ticket->status) === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
            @endforeach
        </flux:select>
        <flux:select name="assigned_to" :label="__('Assigned administrator')" :invalid="$reviewErrors->has('assigned_to')" error:name="assigned_to" :error:bag="$reviewMarker">
            <option value="">{{ __('Unassigned') }}</option>
            @foreach ($admins as $admin)
                <option value="{{ $admin->id }}" @selected(($editingReview ? old('assigned_to') : $ticket->assigned_to) == $admin->id)>{{ $admin->name }}</option>
            @endforeach
        </flux:select>
        <flux:textarea name="resolution" :label="__('Resolution')" placeholder="Describe the resolution" maxlength="5000" :invalid="$reviewErrors->has('resolution')" error:name="resolution" :error:bag="$reviewMarker">{{ $editingReview ? old('resolution') : $ticket->resolution }}</flux:textarea>
    </div>
</x-form-modal>
