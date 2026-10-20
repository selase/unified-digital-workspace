<?php

declare(strict_types=1);

use App\Modules\CmsCore\Database\Factories\PostFactory;
use App\Modules\CmsCore\Database\Factories\PostTypeFactory;
use App\Services\ModuleManager;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::flush();

    $this->tenant = setActiveTenantForTest(null, ['slug' => 'page-route-site']);
    app(ModuleManager::class)->enableForTenant('cms-core', $this->tenant);

    $this->pageType = PostTypeFactory::new()->forTenant((string) $this->tenant->id)->create(['slug' => 'page']);
    $this->host = $this->tenant->slug.'.'.mb_ltrim((string) config('session.domain'), '.');
});

test('a published page is served at /site/{slug} on a tenant subdomain', function (): void {
    PostFactory::new()->published()->forTenant((string) $this->tenant->id)->create([
        'post_type_id' => $this->pageType->id,
        'title' => 'About Our Work',
        'slug' => 'about',
        'body' => '<p>Page body text</p>',
    ]);

    $this->get("http://{$this->host}/site/about", ['HTTP_HOST' => $this->host])
        ->assertOk()
        ->assertSee('About Our Work')
        ->assertSee('Page body text', false);
});

test('unknown slugs and deeper paths under /site return 404', function (): void {
    $this->get("http://{$this->host}/site/does-not-exist", ['HTTP_HOST' => $this->host])->assertNotFound();
    $this->get("http://{$this->host}/site/a/b", ['HTTP_HOST' => $this->host])->assertNotFound();
});

test('an unpublished page is not served at /site/{slug}', function (): void {
    PostFactory::new()->forTenant((string) $this->tenant->id)->create([
        'post_type_id' => $this->pageType->id,
        'title' => 'Secret Draft',
        'slug' => 'secret-draft',
        'status' => 'draft',
    ]);

    $this->get("http://{$this->host}/site/secret-draft", ['HTTP_HOST' => $this->host])->assertNotFound();
});

test('visual blocks render on a page and malicious markup in them is removed', function (): void {
    PostFactory::new()->published()->forTenant((string) $this->tenant->id)->create([
        'post_type_id' => $this->pageType->id,
        'title' => 'Built With Blocks',
        'slug' => 'built-with-blocks',
        'body' => '<p>Plain body</p>',
        'blocks_json' => [
            ['type' => 'hero', 'data' => ['title' => 'Hero From Blocks', 'subtitle' => 'Sub line', 'cta_label' => 'Go', 'cta_url' => '/news']],
            ['type' => 'rich_text', 'data' => ['content' => '<p>Safe paragraph</p><script>window.pwned = true</script>']],
        ],
    ]);

    $response = $this->get("http://{$this->host}/site/built-with-blocks", ['HTTP_HOST' => $this->host])->assertOk();

    $response->assertSee('Hero From Blocks')->assertSee('Safe paragraph', false);
    expect($response->getContent())->not->toContain('window.pwned');
});

test('a page without blocks renders exactly as before', function (): void {
    PostFactory::new()->published()->forTenant((string) $this->tenant->id)->create([
        'post_type_id' => $this->pageType->id,
        'title' => 'Plain Page',
        'slug' => 'plain-page',
        'body' => '<p>Only the body</p>',
    ]);

    $response = $this->get("http://{$this->host}/site/plain-page", ['HTTP_HOST' => $this->host])->assertOk();

    expect($response->getContent())->toContain('Only the body')->not->toContain('cms-page-blocks');
});
