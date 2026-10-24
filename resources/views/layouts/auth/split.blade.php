<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['forceLightAppearance' => true])
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-neutral-950">
        <div class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
            <div class="relative hidden h-full items-center justify-center bg-white p-10 lg:flex">
                <img
                    src="{{ asset('assets/aquaflow-logo-with-title/web-app-manifest-512x512.png') }}"
                    alt="AquaFlow Water Management System"
                    class="w-full max-w-md object-contain"
                >
            </div>
            <div class="flex h-full w-full items-center justify-center bg-[#079EE9] px-8 py-10 lg:p-8">
                <div class="mx-auto flex w-full flex-col justify-center space-y-6 rounded-2xl bg-white p-8 shadow-lg dark:bg-neutral-900 sm:w-[350px]">
                    <a href="{{ route('home') }}" class="z-20 flex flex-col items-center gap-2 font-medium lg:hidden" wire:navigate>
                        <span class="flex h-9 w-9 items-center justify-center rounded-md">
                            <x-app-logo-icon class="size-9 fill-current text-black dark:text-white" />
                        </span>

                        <span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
                    </a>
                    {{ $slot }}
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
