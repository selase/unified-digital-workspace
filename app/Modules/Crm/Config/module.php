<?php

declare(strict_types=1);

return [
    'name' => 'CRM & Client Lifecycle',
    'slug' => 'crm',
    'version' => '1.0.0',
    'description' => 'Enterprise CRM for walk-in & website inquiries, client accounts, contacts, deals pipeline, and client portal onboarding.',

    'namespace' => 'App\\Modules\\Crm',
    'provider' => 'App\\Modules\\Crm\\Providers\\CrmServiceProvider',

    'tier' => 'standard',
    'is_billable' => true,

    'depends_on' => ['core'],
    'conflicts_with' => [],

    'features' => [
        'crm.manage' => [
            'type' => 'boolean',
            'name' => 'CRM & Client Pipeline',
            'description' => 'Manage leads, walk-in inquiries, accounts, contacts, and deals.',
        ],
    ],

    'permissions' => [
        'crm.view',
        'crm.leads.manage',
        'crm.accounts.manage',
        'crm.deals.manage',
        'crm.convert',
    ],

    'routes' => [
        'web' => true,
        'api' => false,
    ],

    'author' => 'UDW Team',
    'homepage' => null,
    'support' => null,
];
