@props([
    'status',
])

@if ($status)
    <x-feedback-toast :text="$status === 'verification-link-sent' ? __('A new verification link has been sent to your email address.') : $status" />
@endif
