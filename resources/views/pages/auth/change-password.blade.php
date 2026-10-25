<x-layouts::auth :title="__('Change password')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Choose a new password')" :description="__('Your temporary password must be replaced before you continue.')" />

        <form method="POST" action="{{ route('password.change.store') }}" class="flex flex-col gap-6">
            @csrf
            <flux:input name="password" type="password" :label="__('New password')" required autocomplete="new-password" viewable />
            <flux:input name="password_confirmation" type="password" :label="__('Confirm new password')" required autocomplete="new-password" viewable />
            <flux:button type="submit" variant="primary">{{ __('Save password') }}</flux:button>
        </form>
    </div>
</x-layouts::auth>
