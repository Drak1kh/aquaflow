<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800" x-data="{
        sidebarSearch: '',
        matchesSidebarItem(label) {
            return label.trim().toLocaleLowerCase().includes(this.sidebarSearch.trim().toLocaleLowerCase());
        },
        get sidebarHasResults() {
            return [...document.querySelectorAll('[data-flux-sidebar-item]')].some(item => this.matchesSidebarItem(item.textContent));
        },
    }">
        @php
            $navProviders = auth()->user()->is_super_admin
                ? \App\Models\WaterProvider::where('status', 'active')->orderBy('name')->get()
                : auth()->user()->memberships()->where('status', 'active')->with('provider')->get()->pluck('provider')->filter(fn ($provider) => $provider?->status === 'active');
            $navProviderId = session('active_provider_id') ?? $navProviders->first()?->id;
        @endphp
        <flux:sidebar sticky collapsible class="border-e border-zinc-200 bg-zinc-50 transition-[width,padding,transform] duration-300 ease-in-out motion-reduce:transition-none dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <a href="{{ route('dashboard') }}" wire:navigate aria-label="{{ __('AquaFlow dashboard') }}" class="relative flex h-16 min-w-0 flex-1 items-center overflow-hidden in-data-flux-sidebar-collapsed-desktop:mx-auto in-data-flux-sidebar-collapsed-desktop:w-10 in-data-flux-sidebar-collapsed-desktop:flex-none">
                    <img src="{{ asset('assets/aquaflow-banner-image.png') }}" alt="AquaFlow" class="absolute left-0 -top-1 w-[232px] max-w-none in-data-flux-sidebar-collapsed-desktop:hidden" />
                    <img src="{{ asset('assets/aquaflow-logo/favicon.svg') }}" alt="" class="hidden size-10 in-data-flux-sidebar-collapsed-desktop:block" />
                </a>
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:separator />

            <div class="in-data-flux-sidebar-collapsed-desktop:hidden">
                <flux:input
                    x-ref="sidebarSearchInput"
                    x-model="sidebarSearch"
                    x-on:keydown.escape="sidebarSearch = ''; $el.blur()"
                    type="search"
                    icon="search"
                    clearable
                    autocomplete="off"
                    :placeholder="__('Search menu...')"
                    :aria-label="__('Search sidebar menu')"
                />
            </div>

            <flux:tooltip :content="__('Search menu')" position="right" class="hidden in-data-flux-sidebar-collapsed-desktop:block">
                <flux:button
                    variant="subtle"
                    icon="search"
                    class="w-10"
                    :aria-label="__('Search menu')"
                    x-on:click="$dispatch('flux-sidebar-toggle'); $nextTick(() => $refs.sidebarSearchInput.focus())"
                />
            </flux:tooltip>

            <flux:sidebar.nav>
                <flux:sidebar.item icon="house" :href="route('dashboard')" :current="request()->routeIs('dashboard')" x-show="matchesSidebarItem($el.textContent)" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>
                @if (auth()->user()->is_super_admin)
                    <flux:sidebar.item icon="user-group" :href="route('admin.index')" :current="request()->routeIs('admin.*')" x-show="matchesSidebarItem($el.textContent)" wire:navigate>Administration</flux:sidebar.item>
                    <flux:sidebar.item icon="chart-bar" :href="route('admin.overview')" :current="request()->routeIs('admin.overview')" x-show="matchesSidebarItem($el.textContent)" wire:navigate>Platform oversight</flux:sidebar.item>
                    <flux:sidebar.item icon="chat-bubble-left-right" :href="route('admin.support')" :current="request()->routeIs('admin.support*')" x-show="matchesSidebarItem($el.textContent)" wire:navigate>Escalated support</flux:sidebar.item>
                    <flux:sidebar.item icon="cog-6-tooth" :href="route('admin.settings')" :current="request()->routeIs('admin.settings*')" x-show="matchesSidebarItem($el.textContent)" wire:navigate>System settings</flux:sidebar.item>
                @endif
                @if ($navProviderId)
                    <flux:sidebar.item icon="users" :href="route('provider.index')" :current="request()->routeIs('provider.index')" x-show="matchesSidebarItem($el.textContent)" wire:navigate>Consumers & connections</flux:sidebar.item>
                    <flux:sidebar.item icon="document-text" :href="route('provider.billing')" :current="request()->routeIs('provider.billing', 'provider.bills.*')" x-show="matchesSidebarItem($el.textContent)" wire:navigate>Billing</flux:sidebar.item>
                    <flux:sidebar.item icon="banknotes" :href="route('provider.payments')" :current="request()->routeIs('provider.payments*')" x-show="matchesSidebarItem($el.textContent)" wire:navigate>Payments</flux:sidebar.item>
                    <flux:sidebar.item icon="wrench-screwdriver" :href="route('provider.service')" :current="request()->routeIs('provider.service*')" x-show="matchesSidebarItem($el.textContent)" wire:navigate>Service status</flux:sidebar.item>
                    <flux:sidebar.item icon="chart-bar" :href="route('provider.report')" :current="request()->routeIs('provider.report')" x-show="matchesSidebarItem($el.textContent)" wire:navigate>Reports</flux:sidebar.item>
                    <flux:sidebar.item icon="chat-bubble-left-right" :href="route('provider.support')" :current="request()->routeIs('provider.support*')" x-show="matchesSidebarItem($el.textContent)" wire:navigate>Support</flux:sidebar.item>
                @endif
                @if (auth()->user()->consumers()->exists())
                    <flux:sidebar.item icon="user-circle" :href="route('consumer.index')" :current="request()->routeIs('consumer.*')" x-show="matchesSidebarItem($el.textContent)" wire:navigate>My account</flux:sidebar.item>
                    <flux:sidebar.item icon="chat-bubble-left-right" :href="route('consumer.support')" :current="request()->routeIs('consumer.support*')" x-show="matchesSidebarItem($el.textContent)" wire:navigate>Help & support</flux:sidebar.item>
                @endif
            </flux:sidebar.nav>

            @if ($navProviders->count() > 1 || (auth()->user()->is_super_admin && $navProviders->isNotEmpty()))
                <form method="POST" action="{{ route('providers.switch') }}" class="flex flex-col gap-2 px-3 py-3 in-data-flux-sidebar-collapsed-desktop:hidden">
                    @csrf
                    <label for="active-provider" class="text-xs font-medium">Active provider</label>
                    <select id="active-provider" name="water_provider_id" class="rounded border border-zinc-300 bg-white p-2 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                        @foreach ($navProviders as $provider)
                            <option value="{{ $provider->id }}" @selected($navProviderId == $provider->id)>{{ $provider->name }}</option>
                        @endforeach
                    </select>
                    <flux:button type="submit" size="sm">Switch</flux:button>
                </form>
            @endif

            <p x-cloak x-show="sidebarSearch.trim() !== '' && !sidebarHasResults" class="[x-cloak]:hidden px-3 text-sm text-zinc-500 in-data-flux-sidebar-collapsed-desktop:hidden dark:text-zinc-400">
                {{ __('No menu items found') }}
            </p>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="folder-git-2" href="https://github.com/laravel/livewire-starter-kit" target="_blank" x-show="matchesSidebarItem($el.textContent)">
                    {{ __('Repository') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="book-open-text" href="https://laravel.com/docs/starter-kits#livewire" target="_blank" x-show="matchesSidebarItem($el.textContent)">
                    {{ __('Documentation') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

        </flux:sidebar>

        @php
            $pageTitle = match (request()->route()?->getName()) {
                'profile.edit' => __('Profile'),
                'security.edit' => __('Security'),
                'appearance.edit' => __('Appearance'),
                'admin.index' => __('Administration'),
                'provider.index' => __('Provider workspace'),
                'provider.billing' => __('Billing'),
                'provider.payments' => __('Payments'),
                'provider.service' => __('Service status'),
                'provider.report' => __('Reports'),
                'consumer.index' => __('My account'),
                default => __('Dashboard'),
            };
        @endphp

        <flux:header class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle icon="menu" inset="left" />

            <flux:breadcrumbs class="min-w-0">
                <flux:breadcrumbs.item :href="route('dashboard')" icon="house" wire:navigate>{{ __('Main') }}</flux:breadcrumbs.item>
                @if (request()->routeIs('dashboard'))
                    <flux:breadcrumbs.item>{{ $pageTitle }}</flux:breadcrumbs.item>
                @else
                    <flux:breadcrumbs.item :href="route('profile.edit')" wire:navigate>{{ __('Settings') }}</flux:breadcrumbs.item>
                    <flux:breadcrumbs.item>{{ $pageTitle }}</flux:breadcrumbs.item>
                @endif
            </flux:breadcrumbs>

            <flux:spacer />

            <flux:tooltip :content="__('Toggle appearance')" position="bottom">
                <flux:button variant="ghost" size="sm" icon="moon" icon:variant="outline" aria-label="{{ __('Toggle appearance') }}" x-data x-on:click="$flux.appearance = $flux.appearance === 'dark' ? 'light' : 'dark'" />
            </flux:tooltip>

            <flux:dropdown position="bottom" align="end">
                <flux:button variant="ghost" size="sm" icon="bell" icon:variant="outline" aria-label="{{ __('Notifications') }}" />

                <flux:menu>
                    <div class="px-3 py-2 text-sm text-zinc-500 dark:text-white/80">{{ __('No notifications yet') }}</div>
                </flux:menu>
            </flux:dropdown>

            <flux:dropdown position="bottom" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    :name="auth()->user()->name"
                    icon:trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
