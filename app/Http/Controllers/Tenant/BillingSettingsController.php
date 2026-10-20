<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateBillingSettingsRequest;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class BillingSettingsController extends Controller
{
    public function index(TenantContext $tenantContext): View
    {
        $tenant = $tenantContext->getTenant();

        return view('tenant.settings.billing', [
            'tenant' => $tenant,
            'billingEmail' => $tenant->meta['billing_email'] ?? $tenant->email,
            'taxId' => $tenant->meta['tax_id'] ?? '',
            'billingAddress' => $tenant->meta['billing_address'] ?? $tenant->address,
            'breadcrumbs' => [
                ['name' => 'Billing', 'link' => route('tenant.billing.index')],
                ['name' => 'Settings'],
            ],
        ]);
    }

    public function update(UpdateBillingSettingsRequest $request, TenantContext $tenantContext): RedirectResponse
    {
        $tenant = $tenantContext->getTenant();

        $validated = $request->validated();

        $meta = $tenant->meta ?? [];
        $meta['billing_email'] = $validated['billing_email'];
        foreach (['tax_id', 'billing_address'] as $field) {
            if (array_key_exists($field, $validated)) {
                $meta[$field] = $validated[$field];
            }
        }

        $tenant->update(['meta' => $meta, ...array_intersect_key($validated, ['billing_currency' => true])]);

        return back()->with('success', 'Billing settings updated successfully.');
    }
}
