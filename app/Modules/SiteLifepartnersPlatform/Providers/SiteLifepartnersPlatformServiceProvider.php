<?php

declare(strict_types=1);

namespace App\Modules\SiteLifepartnersPlatform\Providers;

use App\Modules\CmsCore\Providers\CmsSiteServiceProvider;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\Events\RequestHandled;

final class SiteLifepartnersPlatformServiceProvider extends CmsSiteServiceProvider
{
    public function getModuleSlug(): string
    {
        return 'site-lifepartners-platform';
    }

    public function getModuleName(): string
    {
        return 'SiteLifepartnersPlatform';
    }

    public function getTenantSlug(): string
    {
        return 'lifepartners-platform';
    }

    protected function bootModule(): void
    {
        $this->app['events']->listen(RequestHandled::class, function (RequestHandled $event): void {
            $tenant = $this->app->make(TenantContext::class)->getTenant();
            if ($tenant?->slug === $this->getTenantSlug()
                && ! ($tenant->meta['launched'] ?? false)
                && $event->request->routeIs('cms-core.website.*', 'cms-core.public.*')) {
                $event->response->headers->set('X-Robots-Tag', 'noindex, nofollow');
            }
        });
    }
}
