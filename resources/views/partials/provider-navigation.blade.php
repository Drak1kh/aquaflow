                    @foreach ([
                        'provider.consumers.index' => ['Consumer Management', 'users'],
                        'provider.readings.index' => ['Meter Readings', 'chart-bar'],
                        'provider.billing.index' => ['Billing Management', 'document-text'],
                        'provider.payments.index' => ['Payment Management', 'credit-card'],
                        'provider.balances.index' => ['Outstanding Balances', 'banknotes'],
                        'provider.service.index' => ['Disconnection & Reconnection', 'arrows-right-left'],
                        'provider.reports.billing' => ['Billing Reports', 'document-text'],
                        'provider.reports.consumption' => ['Water Consumption Reports', 'chart-pie'],
                    ] as $name => [$label, $icon])
                        @can('permission', match ($name) {
                            'provider.consumers.index' => 'consumers.view', 'provider.readings.index', 'provider.service.index' => 'connections.view', 'provider.billing.index' => 'billing.view', 'provider.payments.index', 'provider.balances.index' => 'payments.view', default => 'reports.view',
                        })
                        <flux:sidebar.item :icon="$icon" :href="route($name)" :current="request()->routeIs($name)" wire:navigate>{{ __($label) }}</flux:sidebar.item>
                        @endcan
                    @endforeach
                    @can('provider.settings.manage')
                        <flux:separator class="my-3" />
                        <flux:sidebar.item icon="cog-6-tooth" :href="route('provider.profile.edit')" :current="request()->routeIs('provider.profile.edit')" wire:navigate>Profile Settings</flux:sidebar.item>
                    @endcan
                    @can('tickets.view')
                        <flux:sidebar.item icon="lifebuoy" :href="route('provider.tickets.index')" :current="request()->routeIs('provider.tickets.index')" wire:navigate>Support Tickets</flux:sidebar.item>
                    @endcan
