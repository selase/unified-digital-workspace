<?php

declare(strict_types=1);

require_once __DIR__.'/SiteTenantHelper.php';

use App\Modules\CmsCore\Database\Factories\CategoryFactory;
use App\Modules\CmsCore\Database\Factories\MediaFactory;
use App\Modules\CmsCore\Database\Factories\PostFactory;
use App\Modules\CmsCore\Database\Factories\PostTypeFactory;
use App\Modules\CmsCore\Database\Factories\TagFactory;
use App\Modules\CmsCore\Models\Post;
use App\Modules\CmsCore\Models\PostType;
use App\Modules\CmsCore\Services\CmsSitemapService;
use Illuminate\Support\Facades\Cache;

describe('Sitemap', function (): void {
    test('lists only categories and tags that have published posts', function (): void {
        $tenant = tgfSiteTenant();
        Cache::flush();
        $tenantId = (string) $tenant->id;
        $postType = PostType::query()->where('tenant_id', $tenantId)->where('slug', 'post')->first() ?? PostTypeFactory::new()->forTenant($tenantId)->create(['slug' => 'post']);
        $full = CategoryFactory::new()->create(['tenant_id' => $tenantId, 'slug' => 'has-posts', 'is_active' => true]);
        CategoryFactory::new()->create(['tenant_id' => $tenantId, 'slug' => 'no-posts', 'is_active' => true]);
        $usedTag = TagFactory::new()->create(['tenant_id' => $tenantId, 'slug' => 'used-tag']);
        TagFactory::new()->create(['tenant_id' => $tenantId, 'slug' => 'unused-tag']);
        $post = PostFactory::new()->published()->forTenant($tenantId)->create(['post_type_id' => $postType->id, 'slug' => 'a-story']);
        $post->categories()->attach($full->id);
        $post->tags()->attach($usedTag->id);

        $xml = app(CmsSitemapService::class)->xml($tenantId, 'https://thyroidghanafoundation.org', true);

        expect($xml)->toContain('/category/has-posts')->toContain('/tag/used-tag')->not->toContain('/category/no-posts')->not->toContain('/tag/unused-tag');
    });
});

describe('Empty archives', function (): void {
    test('tell search engines not to index a category or tag with no posts', function (): void {
        $tenant = tgfSiteTenant();
        $tenantId = (string) $tenant->id;
        $postType = PostType::query()->where('tenant_id', $tenantId)->where('slug', 'post')->first() ?? PostTypeFactory::new()->forTenant($tenantId)->create(['slug' => 'post']);
        $full = CategoryFactory::new()->create(['tenant_id' => $tenantId, 'slug' => 'has-posts', 'is_active' => true]);
        CategoryFactory::new()->create(['tenant_id' => $tenantId, 'slug' => 'no-posts', 'is_active' => true]);
        TagFactory::new()->create(['tenant_id' => $tenantId, 'slug' => 'unused-tag']);
        PostFactory::new()->published()->forTenant($tenantId)->create(['post_type_id' => $postType->id, 'slug' => 'a-story'])->categories()->attach($full->id);

        $this->get(TGF_SITE.'/category/no-posts')->assertOk()->assertSee('<meta name="robots" content="noindex, follow"', false);
        $this->get(TGF_SITE.'/tag/unused-tag')->assertOk()->assertSee('<meta name="robots" content="noindex, follow"', false);
        $this->get(TGF_SITE.'/category/has-posts')->assertOk()->assertDontSee('noindex', false);
    });
});

function tgfFeature(string $slug, int $width, int $height): App\Modules\CmsCore\Models\Media
{
    $tenant = app(App\Services\Tenancy\TenantContext::class)->getTenant();
    $media = MediaFactory::new()->create(['tenant_id' => $tenant->id, 'disk' => 'tenant', 'width' => $width, 'height' => $height, 'alt_text' => 'A hero test photo']);
    Post::query()->where('tenant_id', $tenant->id)->where('slug', $slug)->update(['featured_media_id' => $media->id]);

    return $media;
}

describe('Photo heroes', function (): void {
    test('a landscape featured image becomes the page hero with sized variants', function (): void {
        tgfSiteTenant();
        $media = tgfFeature('about', 1600, 900);

        $html = $this->get(TGF_SITE.'/about')->assertOk()->getContent();

        expect($html)->toContain('class="tgf-hero-image')->toContain('width=768 768w')->toContain('width=1200 1200w')->toContain(route('cms-core.media.serve', $media));
    });

    test('a portrait featured image stays in the page body, not the hero', function (): void {
        tgfSiteTenant();
        $media = tgfFeature('about', 800, 1000);

        $html = $this->get(TGF_SITE.'/about')->assertOk()->getContent();

        expect($html)->not->toContain('tgf-hero-image')->toContain(route('cms-core.media.serve', $media));
    });

    test('pages without a featured image keep the plain banner', function (): void {
        tgfSiteTenant();

        expect($this->get(TGF_SITE.'/about')->assertOk()->getContent())->not->toContain('tgf-hero-image');
    });

    test('the membership, donate, volunteer and partner pages use their page image too', function (): void {
        tgfSiteTenant();
        foreach (['membership', 'donate', 'volunteer', 'partner-us'] as $slug) {
            tgfFeature($slug, 1600, 900);
        }

        foreach (['membership', 'donate', 'volunteer', 'partner-us'] as $slug) {
            expect($this->get(TGF_SITE.'/'.$slug)->assertOk()->getContent())->toContain('class="tgf-hero-image');
        }
    });
});

describe('Page enhancements', function (): void {
    test('the FAQ page shows collapsible questions and the challenge page shows statistic tiles', function (): void {
        tgfSiteTenant();

        $this->get(TGF_SITE.'/faq')->assertOk()->assertSee('<details class="tgf-faq-item"', false)->assertSee('<summary>', false);
        $this->get(TGF_SITE.'/the-challenge')->assertOk()->assertSee('class="tgf-stats"', false)->assertSee('class="tgf-stat"', false);
    });
});
