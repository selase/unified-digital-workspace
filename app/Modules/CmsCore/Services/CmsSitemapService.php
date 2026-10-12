<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Services;

use App\Modules\CmsCore\Models\Category;
use App\Modules\CmsCore\Models\Post;
use App\Modules\CmsCore\Models\Tag;
use Illuminate\Support\Facades\Cache;

/**
 * Builds the sitemap for the tenant in the current context.
 *
 * Output depends on how the site is reached: custom domains expose pages at
 * the root (`cms-core.website.*` routes) while subdomains use the `/site`
 * prefix (`cms-core.public.*`). Results are cached per tenant and mode.
 */
final class CmsSitemapService
{
    private const CACHE_TTL_SECONDS = 3600;

    public static function forget(string $tenantId): void
    {
        Cache::forget(self::cacheKey($tenantId, true));
        Cache::forget(self::cacheKey($tenantId, false));
    }

    /**
     * @param  string  $baseUrl  Scheme and host the site is served from, e.g. https://example.org
     */
    public function xml(string $tenantId, string $baseUrl, bool $websiteMode): string
    {
        return Cache::remember(
            self::cacheKey($tenantId, $websiteMode),
            self::CACHE_TTL_SECONDS,
            fn (): string => $this->buildXml($this->urls($baseUrl, $websiteMode)),
        );
    }

    private static function cacheKey(string $tenantId, bool $websiteMode): string
    {
        return 'cms:sitemap:'.$tenantId.':'.($websiteMode ? 'website' : 'site');
    }

    /**
     * @return array<int, array{loc: string, lastmod?: string|null, changefreq: string, priority: string}>
     */
    private function urls(string $baseUrl, bool $websiteMode): array
    {
        $prefix = $websiteMode ? 'cms-core.website.' : 'cms-core.public.';
        $base = mb_rtrim($baseUrl, '/');
        $loc = fn (string $name, mixed $parameters = []): string => $base.route($prefix.$name, $parameters, false);

        $urls = [
            ['loc' => $loc('home'), 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => $loc('posts.archive'), 'changefreq' => 'daily', 'priority' => '0.8'],
            ['loc' => $loc('newsletters.archive'), 'changefreq' => 'weekly', 'priority' => '0.5'],
        ];

        $published = fn (string $type) => Post::query()
            ->published()
            ->forType($type)
            ->select(['slug', 'updated_at']);

        $published('page')->where('slug', '!=', 'home')->each(function (Post $page) use (&$urls, $loc): void {
            $urls[] = ['loc' => $loc('pages.show', $page->slug), 'lastmod' => $page->updated_at?->toW3cString(), 'changefreq' => 'weekly', 'priority' => '0.8'];
        });

        $published('post')->latest('published_at')->each(function (Post $post) use (&$urls, $loc): void {
            $urls[] = ['loc' => $loc('posts.show', $post->slug), 'lastmod' => $post->updated_at?->toW3cString(), 'changefreq' => 'monthly', 'priority' => '0.6'];
        });

        $published('newsletter')->latest('published_at')->each(function (Post $newsletter) use (&$urls, $loc): void {
            $urls[] = ['loc' => $loc('newsletters.show', $newsletter->slug), 'lastmod' => $newsletter->updated_at?->toW3cString(), 'changefreq' => 'monthly', 'priority' => '0.5'];
        });

        Category::query()->where('is_active', true)->select(['slug'])->each(function (Category $category) use (&$urls, $loc): void {
            $urls[] = ['loc' => $loc('posts.category', $category->slug), 'changefreq' => 'weekly', 'priority' => '0.5'];
        });

        Tag::query()->select(['slug'])->each(function (Tag $tag) use (&$urls, $loc): void {
            $urls[] = ['loc' => $loc('posts.tag', $tag->slug), 'changefreq' => 'weekly', 'priority' => '0.4'];
        });

        return $urls;
    }

    /**
     * @param  array<int, array{loc: string, lastmod?: string|null, changefreq: string, priority: string}>  $urls
     */
    private function buildXml(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $url) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.htmlspecialchars($url['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8')."</loc>\n";

            if (! empty($url['lastmod'])) {
                $xml .= "    <lastmod>{$url['lastmod']}</lastmod>\n";
            }

            $xml .= "    <changefreq>{$url['changefreq']}</changefreq>\n";
            $xml .= "    <priority>{$url['priority']}</priority>\n";
            $xml .= "  </url>\n";
        }

        return $xml.'</urlset>';
    }
}
