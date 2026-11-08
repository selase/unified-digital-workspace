<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\Tenant;
use App\Modules\Finance\Services\ChartOfAccounts;
use App\Modules\Finance\Services\FinanceSettings;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;

trait ResolvesFinanceTenant
{
    protected function tenantId(Request $request): string
    {
        $tenant = $request->attributes->get('tenant');
        if ($tenant instanceof Tenant) {
            return (string) $tenant->id;
        }

        $active = app(TenantContext::class)->getTenant();
        if ($active instanceof Tenant) {
            return (string) $active->id;
        }

        $sessionTenantId = $request->session()->get('active_tenant_id');
        abort_if(! is_string($sessionTenantId) || $sessionTenantId === '', 403, 'No active organisation.');

        return $sessionTenantId;
    }

    protected function allow(Request $request, string $permission): string
    {
        abort_unless($request->user()?->can($permission), 403);

        $tenantId = $this->tenantId($request);
        app(ChartOfAccounts::class)->ensureSeeded($tenantId);
        app(FinanceSettings::class)->for($tenantId);

        return $tenantId;
    }

    /** Users are referenced by their uuid across the platform. */
    protected function actorId(Request $request): ?string
    {
        $uuid = $request->user()?->uuid;

        return $uuid ? (string) $uuid : null;
    }
}
