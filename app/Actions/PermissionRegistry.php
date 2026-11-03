<?php

namespace App\Actions;

class PermissionRegistry
{
    /** @var array<string, string> */
    public const LABELS = [
        'consumers.view' => 'View consumers',
        'consumers.manage' => 'Manage consumers',
        'connections.view' => 'View connections',
        'connections.manage' => 'Manage connections and readings',
        'billing.view' => 'View bills',
        'billing.manage' => 'Generate bills and set rates',
        'payments.view' => 'View payments',
        'payments.manage' => 'Record payments',
        'payments.verify' => 'Verify payments',
        'reports.view' => 'View provider reports',
        'provider.settings.manage' => 'Update provider profile and billing settings',
        'tickets.view' => 'View support tickets',
        'tickets.create' => 'Submit support tickets',
        'tickets.respond' => 'Respond and escalate tickets',
    ];

    /** @var array<string, list<string>> */
    public const DEFAULTS = [
        'water_provider' => [
            'consumers.view', 'consumers.manage', 'connections.view', 'connections.manage',
            'billing.view', 'billing.manage', 'payments.view', 'payments.manage', 'payments.verify',
            'tickets.view', 'tickets.respond', 'reports.view', 'provider.settings.manage',
        ],
        'consumer' => ['billing.view', 'payments.view', 'tickets.view', 'tickets.create', 'tickets.respond'],
    ];
}
