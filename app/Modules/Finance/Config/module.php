<?php

declare(strict_types=1);

return [
    'name' => 'Finance & Accounting',
    'slug' => 'finance',
    'version' => '1.0.0',
    'description' => 'Double-entry accounting: chart of accounts, journals, customers, vendors, quotes, invoices, bills, expenses, bank reconciliation, recurring invoices, payment links and receivables and payables reporting.',

    'namespace' => 'App\\Modules\\Finance',
    'provider' => 'App\\Modules\\Finance\\Providers\\FinanceServiceProvider',

    'tier' => 'premium',
    'is_billable' => true,

    'depends_on' => ['core'],
    'conflicts_with' => [],

    'features' => [
        'finance.manage' => [
            'type' => 'boolean',
            'name' => 'Finance & Accounting',
            'description' => 'Ledger, invoicing and receivables for the organisation\'s own books.',
        ],
    ],

    'permissions' => [
        'finance.view',
        'finance.accounts.manage',
        'finance.journals.post',
        'finance.contacts.manage',
        'finance.invoices.manage',
        'finance.payments.manage',
        'finance.bills.manage',
        'finance.expenses.manage',
        'finance.bank.manage',
        'finance.budgets.manage',
        'finance.settings.manage',
    ],

    'routes' => [
        'web' => true,
        'api' => false,
    ],

    'author' => 'UDW Team',
    'homepage' => null,
    'support' => null,
];
