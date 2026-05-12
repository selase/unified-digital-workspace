<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant custom domains host the public CMS site only — admin/auth lives on
 * the landlord or tenant subdomain so the session cookie scope (set via
 * SESSION_DOMAIN to the landlord) actually applies.
 *
 * If someone lands on /login (or any other admin path) on the custom domain,
 * the cookie can't be stored and every POST returns 419. Redirect them to
 * the same path on the tenant's admin subdomain instead.
 */
final class RedirectAdminFromCustomDomain
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->tenantContext->getTenant();

        if (! $this->isOnVerifiedCustomDomain($request, $tenant)) {
            return $next($request);
        }

        if (! $this->isAdminRoute($request)) {
            return $next($request);
        }

        $adminHost = $tenant->slug.'.'.mb_ltrim((string) config('session.domain'), '.');
        $target = $request->getScheme().'://'.$adminHost.$request->getRequestUri();

        return redirect()->away($target);
    }

    private function isOnVerifiedCustomDomain(Request $request, ?Tenant $tenant): bool
    {
        return $tenant !== null
            && $tenant->custom_domain !== null
            && $tenant->custom_domain_status === 'active'
            && mb_strtolower($request->getHost()) === mb_strtolower($tenant->custom_domain);
    }

    /**
     * A request is "admin" unless it matches a known public-facing route name.
     *
     * Public-facing on a custom domain:
     *   - module.website.* (CMS pages registered via cms.website middleware)
     *   - site-icon.* (favicon, apple-touch-icon, manifest, sized PNGs)
     */
    private function isAdminRoute(Request $request): bool
    {
        $route = $request->route();
        $name = (string) ($route?->getName() ?? '');

        if ($name === '') {
            return true;
        }

        if (preg_match('/^[a-z0-9-]+\.website\./', $name) === 1) {
            return false;
        }

        if (str_starts_with($name, 'site-icon.')) {
            return false;
        }

        return true;
    }
}
