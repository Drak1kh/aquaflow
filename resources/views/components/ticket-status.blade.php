@props(['status'])

<flux:badge size="sm" :color="match ($status) {
    'open' => 'amber',
    'under_review', 'waiting_for_consumer' => 'cyan',
    'referred_to_provider' => 'blue',
    'resolved' => 'green',
    default => 'zinc',
}">{{ ucfirst(str_replace('_', ' ', $status)) }}</flux:badge>
