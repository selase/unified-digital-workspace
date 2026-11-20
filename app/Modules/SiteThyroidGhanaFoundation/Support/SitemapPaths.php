<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Support;

use App\Models\Tenant;

/** The Thyroid site's own pages that are not CMS posts, offered to the sitemap for the Thyroid tenant only. */
final class SitemapPaths
{
    private const ROUTES = ['events.show', 'board.show', 'impact.show'];

    /**
     * @return list<string>
     */
    public function __invoke(string $tenantId, bool $websiteMode): array
    {
        if (Tenant::query()->whereKey($tenantId)->value('slug') !== 'thyroid-ghana-foundation') {
            return [];
        }

        $prefix = 'site-thyroid-ghana-foundation.'.($websiteMode ? 'website.' : 'public.');

        return array_map(static fn (string $name): string => route($prefix.$name, [], false), self::ROUTES);
    }
}
