<?php

declare(strict_types=1);

namespace App\Modules\Governance\Support;

use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;

trait ResolvesGovernanceTenant
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
        $session = $request->session()->get('active_tenant_id');
        abort_if(! is_string($session) || $session === '', 403, 'No active organisation.');

        return $session;
    }

    protected function allow(Request $request, string $permission): string
    {
        abort_unless($request->user()?->can($permission), 403);

        return $this->tenantId($request);
    }
}
