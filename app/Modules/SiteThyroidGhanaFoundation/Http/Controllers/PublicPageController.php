<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Modules\CmsCore\Services\CmsThemeService;
use App\Modules\Finance\Support\ActivateTenant;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;

abstract class PublicPageController extends Controller
{
    public function __construct(protected readonly CmsThemeService $theme) {}

    protected function tenant(): Tenant
    {
        $tenant = app(TenantContext::class)->getTenant();
        abort_unless($tenant instanceof Tenant, 404);
        ActivateTenant::for($tenant);

        return $tenant;
    }

    protected function moduleOn(string $slug, Tenant $tenant): bool
    {
        return app(ModuleManager::class)->isEnabledForTenant($slug, $tenant);
    }
}
