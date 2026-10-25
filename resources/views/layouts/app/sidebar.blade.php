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
                <flux:sidebar.item icon="house" :href="route('dashboard')" :current="request()->routeIs('dashboard', 'dashboard.*')" x-show="matchesSidebarItem($el.textContent)" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>
                @if (auth()->user()->role === \App\UserRole::SuperAdmin)
                    <flux:sidebar.item icon="users" :href="route('admin.users.index')" :current="request()->routeIs('admin.users.*')" x-show="matchesSidebarItem($el.textContent)" wire:navigate>{{ __('Users') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="building-office" :href="route('admin.providers.index')" :current="request()->routeIs('admin.providers.*')" x-show="matchesSidebarItem($el.textContent)" wire:navigate>{{ __('Providers') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="shield-check" :href="route('admin.permissions.index')" :current="request()->routeIs('admin.permissions.*')" x-show="matchesSidebarItem($el.textContent)" wire:navigate>{{ __('Roles & Permissions') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="document-text" :href="route('admin.billing.index')" :current="request()->routeIs('admin.billing.*')" x-show="matchesSidebarItem($el.textContent)" wire:navigate>{{ __('Billing Oversight') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="credit-card" :href="route('admin.payments.index')" :current="request()->routeIs('admin.payments.*')" x-show="matchesSidebarItem($el.textContent)" wire:navigate>{{ __('Payment Oversight') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="chart-bar" :href="route('admin.reports.index')" :current="request()->routeIs('admin.reports.*')" x-show="matchesSidebarItem($el.textContent)" wire:navigate>{{ __('Reports') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="lifebuoy" :href="route('admin.tickets.index')" :current="request()->routeIs('admin.tickets.*')" x-show="matchesSidebarItem($el.textContent)" wire:navigate>{{ __('Disputes') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="cog-6-tooth" :href="route('admin.settings.index')" :current="request()->routeIs('admin.settings.*')" x-show="matchesSidebarItem($el.textContent)" wire:navigate>{{ __('System Settings') }}</flux:sidebar.item>
                @elseif (auth()->user()->role === \App\UserRole::WaterProvider)
                    <flux:sidebar.item icon="building-office" :href="route('dashboard.provider')" :current="request()->routeIs('provider.*')" x-show="matchesSidebarItem($el.textContent)" wire:navigate>{{ __('Provider Operations') }}</flux:sidebar.item>
                @else
                    <flux:sidebar.item icon="lifebuoy" :href="route('dashboard.consumer')" :current="request()->routeIs('portal.*')" x-show="matchesSidebarItem($el.textContent)" wire:navigate>{{ __('My Account & Support') }}</flux:sidebar.item>
                @endif
            </flux:sidebar.nav>

            <p x-cloak x-show="sidebarSearch.trim() !== '' && !sidebarHasResults" class="[x-cloak]:hidden px-3 text-sm text-zinc-500 in-data-flux-sidebar-collapsed-desktop:hidden dark:text-zinc-400">
                {{ __('No menu items found') }}
            </p>

            <flux:spacer />

        </flux:sidebar>

        @php
            $pageTitle = match (request()->route()?->getName()) {
                'profile.edit' => __('Profile'),
                'security.edit' => __('Security'),
                'appearance.edit' => __('Appearance'),
                'admin.users.index' => __('Users'),
                'admin.providers.index' => __('Providers'),
                'admin.permissions.index' => __('Roles & Permissions'),
                'admin.billing.index', 'admin.billing.show' => __('Billing Oversight'),
                'admin.payments.index' => __('Payment Oversight'),
                'admin.reports.index', 'admin.reports.print' => __('Reports'),
                'admin.tickets.index', 'admin.tickets.show' => __('Disputes'),
                'admin.settings.index' => __('System Settings'),
                default => __('Dashboard'),
            };
        @endphp

        <flux:header class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle icon="menu" inset="left" />

            <flux:breadcrumbs class="min-w-0">
                <flux:breadcrumbs.item :href="route('dashboard')" icon="house" wire:navigate>{{ __('Main') }}</flux:breadcrumbs.item>
                @if (request()->routeIs('dashboard', 'dashboard.*'))
                    <flux:breadcrumbs.item>{{ $pageTitle }}</flux:breadcrumbs.item>
                @else
                    <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ app(\App\Actions\PlatformSettings::class)->get('platform_name') }}</flux:breadcrumbs.item>
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
                                    <flux:text class="truncate">{{ auth()->user()->role->label() }}</flux:text>
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
