<?php

declare(strict_types=1);

return [
    'name' => 'Beneficiaries & Cases',
    'slug' => 'beneficiaries',
    'version' => '1.0.0',
    'description' => 'Beneficiary registers with recorded consent, case management with confidential notes, services delivered, an access log, and anonymisation on request.',
    'namespace' => 'App\\Modules\\Beneficiaries',
    'provider' => 'App\\Modules\\Beneficiaries\\Providers\\BeneficiariesServiceProvider',
    'tier' => 'premium',
    'is_billable' => true,
    'depends_on' => ['core'],
    'conflicts_with' => [],
    'features' => [
        'beneficiaries.manage' => [
            'type' => 'boolean',
            'name' => 'Beneficiaries & Cases',
            'description' => 'Beneficiary register, consent, cases, services and access log for the organisation.',
        ],
    ],
    'permissions' => [
        'beneficiaries.view',
        'beneficiaries.manage',
        'beneficiaries.cases.manage',
        'beneficiaries.confidential.view',
        'beneficiaries.audit.view',
        'beneficiaries.anonymise',
    ],
    'routes' => [
        'web' => true,
        'api' => false,
    ],
    'author' => 'UDW Team',
    'homepage' => null,
    'support' => null,
];
