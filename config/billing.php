<?php

declare(strict_types=1);

return [
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
