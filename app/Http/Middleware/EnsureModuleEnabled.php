<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\CmsCore\Http\Controllers\Web\CmsPublicPageController;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
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
            if ($this->isSitePublicRoute($request)) {
                return $this->showCmsPage($request);
            }

            abort(403, "Module [{$moduleSlug}] is not enabled for this tenant.");
        }

        return $next($request);
    }

    /** A site module's public pages are registered for every host; another tenant's own CMS page at that path must still show. */
    private function isSitePublicRoute(Request $request): bool
    {
        return preg_match('/^site-[a-z0-9-]+\.(website|public)\./', (string) $request->route()?->getName()) === 1;
    }

    private function showCmsPage(Request $request): Response
    {
        $segments = $request->segments();
        $slug = match (true) {
            count($segments) === 1 => $segments[0],
            count($segments) === 2 && $segments[0] === 'site' => $segments[1],
            default => null,
        };
        abort_unless($slug !== null && $request->isMethodSafe(), 404);

        return app(Router::class)->toResponse($request, app(CmsPublicPageController::class)->show($slug));
    }
}
