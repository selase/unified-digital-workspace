<?php

declare(strict_types=1);

use App\Modules\CmsCore\Database\Factories\MediaFactory;
use App\Modules\CmsCore\Database\Factories\PostFactory;
use App\Modules\CmsCore\Database\Factories\PostTypeFactory;
use App\Modules\CmsCore\Models\PostType;
use App\Modules\CmsCore\Services\CmsSitemapService;
use App\Modules\CmsCore\Services\CmsUrlService;
use App\Services\ModuleManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Cache::flush();

    $this->tenant = setActiveTenantForTest(null, [
        'slug' => 'client-site',
        'custom_domain' => 'client-site.example',
        'custom_domain_status' => 'active',
    ]);

    app(ModuleManager::class)->enableForTenant('cms-core', $this->tenant);

    foreach (['page' => 'about', 'post' => 'first-news', 'newsletter' => 'spring-issue'] as $type => $slug) {
        PostFactory::new()->published()->forTenant((string) $this->tenant->id)->create([
            'post_type_id' => PostTypeFactory::new()->forTenant((string) $this->tenant->id)->create(['slug' => $type])->id,
            'title' => ucfirst($slug),
            'slug' => $slug,
        ]);
    }
});

test('sitemap on a custom domain lists root-level urls for the tenant', function (): void {
    $response = $this->get('https://client-site.example/sitemap.xml');

    $response->assertOk()->assertHeader('Content-Type', 'application/xml');

    expect($response->getContent())
        ->toContain('<loc>https://client-site.example/about</loc>')
        ->toContain('<loc>https://client-site.example/news/first-news</loc>')
        ->toContain('<loc>https://client-site.example/newsletters/spring-issue</loc>')
        ->not->toContain('/site/');
});

test('sitemap service uses the /site prefix in subdomain mode', function (): void {
    $xml = app(CmsSitemapService::class)->xml((string) $this->tenant->id, 'https://client-site.udworkspace.com', false);

    expect($xml)
        ->toContain('<loc>https://client-site.udworkspace.com/site/news/first-news</loc>')
        ->toContain('<loc>https://client-site.udworkspace.com/site/about</loc>');
});

test('sitemap is cached per tenant and cleared by the command', function (): void {
    $service = app(CmsSitemapService::class);
    $id = (string) $this->tenant->id;

    $service->xml($id, 'https://client-site.example', true);
    PostFactory::new()->published()->forTenant($id)->create([
        'post_type_id' => PostType::query()->where('slug', 'post')->value('id'),
        'slug' => 'late-addition',
    ]);

    expect($service->xml($id, 'https://client-site.example', true))->not->toContain('late-addition');

    $this->artisan('cms:generate-sitemap', ['--tenant' => $id])->assertSuccessful();

    expect($service->xml($id, 'https://client-site.example', true))->toContain('late-addition');
});

test('uploaded media is served on the custom domain without redirecting away', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('media/logo.jpg', 'binary');

    $media = MediaFactory::new()->create([
        'tenant_id' => (string) $this->tenant->id,
        'disk' => 'public',
        'path' => 'media/logo.jpg',
    ]);

    $response = $this->get('https://client-site.example/cms-media/'.$media->uuid);

    expect($response->getStatusCode())->toBe(200);
});

test('subdomain-authored /site links resolve to root paths on a custom domain only', function (): void {
    $service = new CmsUrlService;
    $websiteMode = new ReflectionProperty($service, 'isWebsiteMode');

    $websiteMode->setValue($service, true);
    expect($service->normalizeUrl('/site/about'))->toBe('/about')
        ->and($service->normalizeUrl('/site'))->toBe('/')
        ->and($service->normalizeUrl('/site?x=1'))->toBe('/?x=1')
        ->and($service->normalizeUrl('/sitemap.xml'))->toBe('/sitemap.xml')
        ->and($service->normalizeUrl('/sitewide'))->toBe('/sitewide')
        ->and($service->normalizeUrl('https://example.org/site/a'))->toBe('https://example.org/site/a')
        ->and($service->normalizeUrl(null))->toBe('');

    $websiteMode->setValue($service, false);
    expect($service->normalizeUrl('/site/about'))->toBe('/site/about');
});
