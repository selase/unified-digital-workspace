<?php

declare(strict_types=1);

namespace App\Services\Email;

use App\Models\Tenant;
use App\Models\TenantEmailDomain;

/** The address a tenant's site mail is sent from: its own verified domain, or null to use the platform default. */
final class TenantSender
{
    /**
     * @return array{address: string, name: string}|null
     */
    public function for(?Tenant $tenant, string $name): ?array
    {
        if ($tenant === null) {
            return null;
        }

        $domain = TenantEmailDomain::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('status', TenantEmailDomain::VERIFIED)->orderBy('created_at')->first();

        return $domain === null ? null : ['address' => $domain->from_address, 'name' => $name];
    }
}
