<?php

declare(strict_types=1);

return [
    /*
     * Supplier details printed on every invoice. Address, TIN and VAT number
     * are only shown when set, so nothing is invented before they are known.
     */
    'supplier' => [
        'name' => env('BILLING_SUPPLIER_NAME', 'Purpledot Limited'),
        'address' => env('BILLING_SUPPLIER_ADDRESS'),
        'tin' => env('BILLING_SUPPLIER_TIN'),
        'vat_number' => env('BILLING_SUPPLIER_VAT_NUMBER'),
        'email' => env('BILLING_SUPPLIER_EMAIL', 'hello@wearepurpledot.com'),
        'phone' => env('BILLING_SUPPLIER_PHONE'),
    ],

    /*
     * Operators emailed when the monthly invoice run fails for any tenant.
     */
    'alert_emails' => array_values(array_filter(array_map('trim', explode(',', (string) env('BILLING_ALERT_EMAILS', 'hiselase@gmail.com,hello@wearepurpledot.com,support@wearepurpledot.com'))))),

    'communications' => [
        'cycle_days' => [7, 1],
        'due_days' => [7, 3, 0],
        'overdue_days' => [7, 14],
    ],
    /*
    |--------------------------------------------------------------------------
    | Global Markup Percentage
    |--------------------------------------------------------------------------
    |
    | This percentage is added to all metered costs by default.
    |
    */
    'global_markup' => (float) env('BILLING_GLOBAL_MARKUP', 0),

    /*
     * Supplier-controlled opt-in. Leave false until VAT registration and
     * commercial rates are approved. Only taxable supplies may use the
     * country rules; administrator-managed tenant meta.tax_exempt overrides
     * all taxes for exempt or zero-rated accounts. Tenant billing settings
     * cannot change this deployment setting or exemption metadata.
     */
    'country_taxes_enabled' => (bool) env('BILLING_COUNTRY_TAXES_ENABLED', false),
];
