<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureModuleEnabled
{
    public function __construct(
        private readonly ModuleManager $moduleManager,
        private readonly TenantContext $tenantContext
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $moduleSlug): Response
    {
        $tenant = $this->tenantContext->getTenant();

        if (! $tenant) {
            abort(403, 'No tenant context available.');
        }

        if (! $this->moduleManager->isEnabledForTenant($moduleSlug, $tenant)) {
            abort(403, "Module [{$moduleSlug}] is not enabled for this tenant.");
        }

        return $next($request);
    }
}
