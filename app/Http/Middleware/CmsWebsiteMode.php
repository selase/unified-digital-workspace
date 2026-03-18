<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CmsWebsiteMode
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ModuleManager $moduleManager
    ) {}

    /**
     * Allow request only when the resolved tenant has an active custom domain
     * that matches the request host and the CMS module is enabled.
     *
     * This middleware gates root-level CMS routes so they only activate on
     * custom domain requests, preventing them from shadowing admin/auth routes
     * on the main domain or subdomain access.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->tenantContext->getTenant();

        if (! $tenant) {
            abort(404);
        }

        // Only activate for verified custom domain requests
        if (
            $tenant->custom_domain === null
            || $tenant->custom_domain_status !== 'active'
            || mb_strtolower($request->getHost()) !== mb_strtolower($tenant->custom_domain)
        ) {
            abort(404);
        }

        // Ensure the CMS module is enabled for this tenant
        if (! $this->moduleManager->isEnabledForTenant('cms-core', $tenant)) {
            abort(404);
        }

        return $next($request);
    }
}
