<?php

uses(Tests\TestCase::class);

test('the shared UI stylesheet defines consistent controls for both themes', function () {
    $css = file_get_contents(base_path('resources/css/app.css'));

    expect($css)
        ->toContain('aquaflow-edit-action')
        ->toContain('aquaflow-danger-action')
        ->toContain('appearance: textfield')
        ->toContain('::-webkit-inner-spin-button')
        ->toContain('.dark');
});

test('accessible modals expose the shared blurred backdrop hook', function () {
    $view = $this->blade('<x-accessible-modal name="example"><input name="name"></x-accessible-modal>');

    expect((string) $view)->toContain('aquaflow-modal');
});

test('action tables use a kebab menu and active status badges', function () {
    $consumers = file_get_contents(base_path('resources/views/provider/consumers.blade.php'));
    $providers = file_get_contents(base_path('resources/views/admin/providers.blade.php'));
    $users = file_get_contents(base_path('resources/views/admin/users.blade.php'));

    expect($consumers.$providers.$users)
        ->toContain('icon="ellipsis-vertical"')
        ->toContain("? 'green' : 'zinc'");
});

test('billing management removes retired period and batch controls', function () {
    $billing = file_get_contents(base_path('resources/views/provider/billing.blade.php'));

    expect($billing)
        ->toContain('Current water rate')
        ->toContain('Six-month rate history')
        ->not->toContain('provider.periods.store')
        ->not->toContain('Generate monthly batch');
});

test('billing management keeps search and billing actions in one divider-free control row', function () {
    $billing = file_get_contents(base_path('resources/views/provider/billing.blade.php'));

    expect($billing)
        ->toContain('id="billing" class="space-y-4"')
        ->toContain('flex flex-wrap items-end gap-3')
        ->not->toContain('lg:border-s');
});

test('billing management renders the latest rate history as bars on a shared baseline', function () {
    $billing = file_get_contents(base_path('resources/views/provider/billing.blade.php'));

    expect($billing)
        ->toContain('h-16 items-end gap-2 border-b')
        ->toContain('rounded-t-sm bg-brand-primary');
});

test('billing management separates its actions from search and uses custom rate tooltips', function () {
    $billing = file_get_contents(base_path('resources/views/provider/billing.blade.php'));

    expect($billing)
        ->toContain('sm:ms-auto')
        ->toContain('$rate->effective_from->format(\'F Y\')')
        ->toContain('<flux:tooltip');
});

test('billing sort controls use the shared combined chevron icon', function () {
    $billing = file_get_contents(base_path('resources/views/provider/billing.blade.php'));

    expect($billing)
        ->toContain('flux:icon.chevrons-up-down')
        ->not->toContain('flux:icon.chevron-up');
});

test('provider dashboard KPI links are neutral white cards without text decoration', function () {
    $dashboard = file_get_contents(base_path('resources/views/provider/dashboard.blade.php'));
    $css = file_get_contents(base_path('resources/css/app.css'));

    expect($dashboard)
        ->toContain('rounded-xl border border-zinc-200 bg-white p-5')
        ->toContain('data-kpi-card')
        ->toContain("route('provider.payments.index')");

    expect($css)->toContain(':not([data-kpi-card])');
});

test('dashboards keep priority information and remove duplicate navigation and activity', function () {
    $provider = file_get_contents(base_path('resources/views/provider/dashboard.blade.php'));
    $providerMetrics = file_get_contents(base_path('resources/views/provider/metrics.blade.php'));
    $admin = file_get_contents(base_path('resources/views/admin/dashboard.blade.php'));
    $consumer = file_get_contents(base_path('resources/views/consumer/dashboard.blade.php'));

    expect($provider.$providerMetrics)
        ->toContain('Operational attention')
        ->toContain('xl:grid-cols-5')
        ->toContain('Latest activity')
        ->not->toContain('Daily workflow')
        ->not->toContain('Recent billing activity')
        ->not->toContain('Recent payments');

    expect($admin)
        ->toContain('Escalated tickets')
        ->not->toContain('Recent system activity');

    expect($consumer)
        ->toContain('Current monthly bill')
        ->not->toContain('View payments')
        ->not->toContain('View notifications')
        ->not->toContain('Get help');
});
