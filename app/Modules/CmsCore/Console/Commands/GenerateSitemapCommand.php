<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Console\Commands;

use App\Modules\CmsCore\Models\Category;
use App\Modules\CmsCore\Models\Post;
use App\Modules\CmsCore\Models\Tag;
use Illuminate\Console\Command;

final class GenerateSitemapCommand extends Command
{
    protected $signature = 'cms:generate-sitemap';

    protected $description = 'Generate a sitemap XML for the CMS public website';

    public function handle(): int
    {
        $urls = collect();

        // Home page
        $urls->push([
            'loc' => route('cms-core.public.home'),
            'changefreq' => 'daily',
            'priority' => '1.0',
        ]);

        // News archive
        $urls->push([
            'loc' => route('cms-core.public.posts.archive'),
            'changefreq' => 'daily',
            'priority' => '0.8',
        ]);

        // Published pages
        Post::query()
            ->published()
            ->forType('page')
            ->where('slug', '!=', 'home')
            ->select(['slug', 'updated_at'])
            ->each(function (Post $page) use ($urls): void {
                $urls->push([
                    'loc' => route('cms-core.public.pages.show', $page->slug),
                    'lastmod' => $page->updated_at?->toW3cString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.8',
                ]);
            });

        // Published posts
        Post::query()
            ->published()
            ->forType('post')
            ->select(['slug', 'updated_at'])
            ->latest('published_at')
            ->each(function (Post $post) use ($urls): void {
                $urls->push([
                    'loc' => route('cms-core.public.posts.show', $post->slug),
                    'lastmod' => $post->updated_at?->toW3cString(),
                    'changefreq' => 'monthly',
                    'priority' => '0.6',
                ]);
            });

        // Categories
        Category::query()
            ->where('is_active', true)
            ->select(['slug'])
            ->each(function (Category $category) use ($urls): void {
                $urls->push([
                    'loc' => route('cms-core.public.posts.category', $category->slug),
                    'changefreq' => 'weekly',
                    'priority' => '0.5',
                ]);
            });

        // Tags
        Tag::query()
            ->select(['slug'])
            ->each(function (Tag $tag) use ($urls): void {
                $urls->push([
                    'loc' => route('cms-core.public.posts.tag', $tag->slug),
                    'changefreq' => 'weekly',
                    'priority' => '0.4',
                ]);
            });

        $xml = $this->buildXml($urls->all());
        $path = storage_path('app/public/sitemap.xml');
        file_put_contents($path, $xml);

        $this->info("Sitemap generated with {$urls->count()} URLs at {$path}");

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array{loc: string, lastmod?: string|null, changefreq?: string, priority?: string}>  $urls
     */
    private function buildXml(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $url) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>{$url['loc']}</loc>\n";
            if (! empty($url['lastmod'])) {
                $xml .= "    <lastmod>{$url['lastmod']}</lastmod>\n";
            }
            if (! empty($url['changefreq'])) {
                $xml .= "    <changefreq>{$url['changefreq']}</changefreq>\n";
            }
            if (! empty($url['priority'])) {
                $xml .= "    <priority>{$url['priority']}</priority>\n";
            }
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return $xml;
    }
}
