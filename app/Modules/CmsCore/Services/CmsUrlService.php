<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * Resolves CMS public route names based on the current request context.
 *
 * When serving on a custom domain (root-level routes), links use the
 * `cms-core.website.*` route names. When serving on a subdomain
 * (/site prefix), links use `cms-core.public.*` route names.
 */
final class CmsUrlService
{
    private ?bool $isWebsiteMode = null;

    /**
     * Generate a URL for a CMS public route.
     */
    public function route(string $name, mixed $parameters = [], bool $absolute = true): string
    {
        $prefix = $this->isWebsiteMode() ? 'cms-core.website.' : 'cms-core.public.';

        return route($prefix.$name, $parameters, $absolute);
    }

    /**
     * Determine if the current request is in website mode (custom domain, root-level).
     */
    public function isWebsiteMode(): bool
    {
        if ($this->isWebsiteMode !== null) {
            return $this->isWebsiteMode;
        }

        $currentRoute = RouteFacade::current();

        if ($currentRoute) {
            $routeName = $currentRoute->getName() ?? '';
            $this->isWebsiteMode = str_starts_with($routeName, 'cms-core.website.');
        } else {
            $this->isWebsiteMode = false;
        }

        return $this->isWebsiteMode;
    }

    /**
     * Get the route name prefix for the current context.
     */
    public function prefix(): string
    {
        return $this->isWebsiteMode() ? 'cms-core.website.' : 'cms-core.public.';
    }
}
