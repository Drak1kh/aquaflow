@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
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
        <flux:sidebar sticky collapsible class="aquaflow-sidebar border-e border-zinc-200 bg-brand-surface transition-[width,padding,transform] duration-300 ease-in-out motion-reduce:transition-none dark:border-zinc-700 dark:bg-zinc-900">
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
                    @foreach ([
                        'admin.users.index' => ['User Management', 'users'],
                        'admin.providers.index' => ['Provider Management', 'building-office'],
                        'admin.permissions.index' => ['Roles & Permissions', 'shield-check'],
                        'admin.billing.index' => ['Billing Oversight', 'document-text'],
                        'admin.payments.index' => ['Payment Oversight', 'credit-card'],
                        'admin.reports.index' => ['Reports & Analytics', 'chart-bar'],
                        'admin.tickets.index' => ['Complaints & Disputes', 'lifebuoy'],
                        'admin.settings.index' => ['System Settings', 'cog-6-tooth'],
                    ] as $name => [$label, $icon])
                        <flux:sidebar.item :icon="$icon" :href="route($name)" :current="request()->routeIs(str_replace('.index', '.*', $name))" x-show="matchesSidebarItem($el.textContent)" wire:navigate>{{ __($label) }}</flux:sidebar.item>
                    @endforeach
                    @if (request()->routeIs('provider.*', 'dashboard.provider', 'management') && auth()->user()->waterProvider)
                        <flux:separator class="my-3" />
                        <p class="px-3 py-2 text-sm font-medium text-brand-ink dark:text-brand-mist in-data-flux-sidebar-collapsed-desktop:hidden">{{ auth()->user()->waterProvider->name }}</p>
                        @include('partials.provider-navigation')
                    @endif
                @elseif (auth()->user()->role !== \App\UserRole::Consumer)
                    @include('partials.provider-navigation')
                @else
                    @foreach (['account' => ['My Account', 'identification'], 'connection' => ['My Connection', 'arrows-right-left'], 'bills' => ['My Bills', 'document-text'], 'payments' => ['Payments', 'credit-card'], 'notifications' => ['Notifications', 'bell'], 'support' => ['Help & Support', 'lifebuoy']] as $section => [$label, $icon])
                        @can(match ($section) {'bills' => 'billing.view', 'payments' => 'payments.view', 'notifications' => 'notifications.view', default => 'access-consumer'})
                            <flux:sidebar.item :icon="$icon" :href="route('portal.section', $section)" :current="request()->routeIs('portal.section') && request()->route('section') === $section" x-show="matchesSidebarItem($el.textContent)" wire:navigate>{{ __($label) }}</flux:sidebar.item>
                        @endcan
                    @endforeach
                    <flux:sidebar.item icon="cog-6-tooth" :href="route('portal.settings')" :current="request()->routeIs('portal.settings')" x-show="matchesSidebarItem($el.textContent)" wire:navigate>Settings</flux:sidebar.item>
                @endif

            </flux:sidebar.nav>

            <p x-cloak x-show="sidebarSearch.trim() !== '' && !sidebarHasResults" class="[x-cloak]:hidden px-3 text-sm text-zinc-500 in-data-flux-sidebar-collapsed-desktop:hidden dark:text-zinc-400">
                {{ __('No menu items found') }}
            </p>

            <flux:spacer />

        </flux:sidebar>

        @php
            $pageTitle = match (request()->route()?->getName()) {
                'portal.settings' => __('Client Settings'),
                'portal.section' => match (request()->route('section')) {'account' => __('My Account'), 'connection' => __('My Connection'), 'bills' => __('My Bills'), 'payments' => __('Payments'), 'notifications' => __('Notifications'), 'support' => __('Help & Support'), default => __('Client Portal')},
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
                'provider.consumers.index' => __('Consumer Management'),
                'provider.readings.index' => __('Meter Readings'),
                'provider.billing.index' => __('Billing Management'),
                'provider.payments.index' => __('Payment Management'),
                'provider.balances.index' => __('Outstanding Balances'),
                'provider.service.index' => __('Disconnection & Reconnection'),
                'provider.reports.billing' => __('Billing Reports'),
                'provider.reports.consumption' => __('Water Consumption Reports'),
                'provider.tickets.index' => __('Support Tickets'),
                'provider.profile.edit' => __('Profile Settings'),
                default => __('Dashboard'),
            };
        @endphp

        <flux:header class="aquaflow-header border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle icon="menu" inset="left" />

            <flux:breadcrumbs class="hidden min-w-0 md:flex">
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
                <flux:button class="min-h-11 min-w-11" variant="ghost" size="sm" icon="moon" icon:variant="outline" aria-label="{{ __('Toggle appearance') }}" x-data x-on:click="$flux.appearance = $flux.appearance === 'dark' ? 'light' : 'dark'" />
            </flux:tooltip>

            @if (auth()->user()->role === \App\UserRole::Consumer)
                @can('notifications.view')<flux:button :href="route('portal.section', 'notifications')" class="min-h-11 min-w-11" variant="ghost" icon="bell" aria-label="Notifications" />@endcan
            @else
            <flux:dropdown position="bottom" align="end">
                <flux:button class="min-h-11 min-w-11" variant="ghost" size="sm" icon="bell" icon:variant="outline" aria-label="{{ __('Notifications') }}" />

                <flux:menu>
                    <div class="px-3 py-2 text-sm text-zinc-500 dark:text-white/80">{{ __('No notifications yet') }}</div>
                </flux:menu>
            </flux:dropdown>
            @endif

            <flux:dropdown position="bottom" align="end">
                <flux:profile
                    class="min-h-11 [&>span]:hidden md:[&>span]:inline"
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
