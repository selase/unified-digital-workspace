<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Services;

use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\View;

/**
 * Resolves CMS Blade views using a convention-based fallback chain:
 *
 * 1. Site module views (if a site-{tenant-slug} module exists)
 * 2. Tenant overrides (Views/tenants/{tenant-slug}/{view})
 * 3. Theme views (Views/themes/{theme-slug}/{view})
 * 4. Default views (Views/public/{view})
 */
final class CmsViewResolver
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly CmsThemeService $themeService
    ) {}

    /**
     * Resolve a view name through the fallback chain.
     *
     * @param  string  $viewName  Relative view name, e.g. 'home', 'layouts.website', 'templates.landing'
     */
    public function resolve(string $viewName): string
    {
        $tenant = $this->tenantContext->getTenant();
        $tenantSlug = $tenant?->slug;

        // 1. Site module views (highest priority)
        if ($tenantSlug) {
            $siteModuleView = "site-{$tenantSlug}::public.{$viewName}";
            if (View::exists($siteModuleView)) {
                return $siteModuleView;
            }
        }

        // 2. Tenant-specific overrides within CMS module
        if ($tenantSlug) {
            $tenantView = "cms-core::tenants.{$tenantSlug}.{$viewName}";
            if (View::exists($tenantView)) {
                return $tenantView;
            }
        }

        // 3. Theme views
        $theme = $this->themeService->activeTheme();
        if ($theme !== 'default') {
            $themeView = "cms-core::themes.{$theme}.{$viewName}";
            if (View::exists($themeView)) {
                return $themeView;
            }
        }

        // 4. Default CMS views
        return "cms-core::public.{$viewName}";
    }

    /**
     * Resolve a layout view name through the fallback chain.
     */
    public function resolveLayout(string $layoutName = 'layouts.website'): string
    {
        return $this->resolve($layoutName);
    }

    /**
     * Resolve a component view name through the fallback chain.
     */
    public function resolveComponent(string $componentName): string
    {
        $tenant = $this->tenantContext->getTenant();
        $tenantSlug = $tenant?->slug;

        // Check tenant override first
        if ($tenantSlug) {
            $siteModuleView = "site-{$tenantSlug}::components.{$componentName}";
            if (View::exists($siteModuleView)) {
                return $siteModuleView;
            }

            $tenantView = "cms-core::tenants.{$tenantSlug}.components.{$componentName}";
            if (View::exists($tenantView)) {
                return $tenantView;
            }
        }

        $theme = $this->themeService->activeTheme();
        if ($theme !== 'default') {
            $themeView = "cms-core::themes.{$theme}.components.{$componentName}";
            if (View::exists($themeView)) {
                return $themeView;
            }
        }

        return "cms-core::components.{$componentName}";
    }
}
