<?php

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;

    public string $name = '';
    public string $email = '';
    public string $phone = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
        $this->phone = Auth::user()->consumer?->phone ?? '';
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([...$this->profileRules($user->id), 'phone' => ['nullable', 'string', 'max:50']]);
        unset($validated['phone']);
        DB::transaction(function () use ($user, $validated): void {
            if ($user->consumer) {
                $validated['name'] = $user->name;
                $user->consumer->update(['phone' => $this->phone, 'email' => $validated['email']]);
            }
            $user->fill($validated);
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }
            $user->save();
        });

        Flux::modal('edit-profile')->close();
        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
}; ?>

<section class="client-page w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Profile')" :subheading="Auth::user()->consumer_id ? __('Update your contact number and email address') : __('Update your name and email address')">
        <div class="my-6 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <div class="min-w-0">
                <flux:heading level="3">{{ Auth::user()->name }}</flux:heading>
                <flux:text class="mt-1 break-all">{{ Auth::user()->email }}</flux:text>
            </div>
            <flux:modal.trigger name="edit-profile">
                <flux:button icon="pencil-square">{{ __('Edit profile') }}</flux:button>
            </flux:modal.trigger>
        </div>

        <x-accessible-modal name="edit-profile" :show="$errors->any()" class="w-full max-w-lg">
            <form wire:submit="updateProfileInformation" class="space-y-6">
                <div>
                    <flux:heading level="2" size="lg">{{ __('Edit profile') }}</flux:heading>
                    <flux:text class="mt-2">{{ Auth::user()->consumer_id ? __('Update your contact number and email address. Your registered name is maintained by your provider.') : __('Update your name and email address.') }}</flux:text>
                </div>
                <flux:input :readonly="Auth::user()->consumer_id !== null" wire:model="name" :label="__('Name')" type="text" required autocomplete="name" />
                <flux:input wire:model="email" placeholder="e.g. account@example.com" :label="__('Email')" type="email" required autocomplete="email" />

                @if (Auth::user()->consumer_id)
                    <flux:input wire:model="phone" placeholder="e.g. 09123456789" label="Contact number" type="tel" />
                @endif
                @if ($this->hasUnverifiedEmail)
                    <div>
                        <flux:text>
                            {{ __('Your email address is unverified.') }}
                            <flux:link class="cursor-pointer text-sm" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>
                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="mt-2 font-medium !text-green-600 !dark:text-green-400">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </flux:text>
                        @endif
                    </div>
                @endif

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" type="submit" data-test="update-profile-button">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </x-accessible-modal>

        <flux:button :href="route('security.edit')">Password and security settings</flux:button>
        @if ($this->showDeleteUser)
            <livewire:pages::settings.delete-user-form />
        @endif
    </x-pages::settings.layout>
</section>
