<?php

declare(strict_types=1);

use App\Modules\CmsCore\Database\Factories\PostFactory;
use App\Modules\CmsCore\Database\Factories\PostTypeFactory;
use App\Modules\CmsCore\Services\CmsViewResolver;
use App\Services\ModuleManager;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::flush();
    $this->tenant = setActiveTenantForTest(null, ['slug' => 'newsletter-route-site']);
    app(ModuleManager::class)->enableForTenant('cms-core', $this->tenant);
    $this->type = PostTypeFactory::new()->forTenant((string) $this->tenant->id)->create(['slug' => 'newsletter']);
    $this->host = $this->tenant->slug.'.'.mb_ltrim((string) config('session.domain'), '.');
});

it('renders the default newsletter archive and links to newsletter details', function (): void {
    $post = PostFactory::new()->published()->forTenant((string) $this->tenant->id)->create([
        'post_type_id' => $this->type->id,
        'title' => 'Our Newsletter',
        'slug' => 'our-newsletter',
    ]);

    $this->get("http://{$this->host}/site/newsletters", ['HTTP_HOST' => $this->host])
        ->assertOk()->assertSee($post->title)->assertSee('/newsletters/our-newsletter');

    $this->get("http://{$this->host}/site/newsletters/our-newsletter", ['HTTP_HOST' => $this->host])
        ->assertOk()->assertSee($post->title);
});

it('renders an empty newsletter archive', function (): void {
    $this->get("http://{$this->host}/site/newsletters", ['HTTP_HOST' => $this->host])
        ->assertOk()->assertSee('No newsletters published yet.');
});

it('keeps site-specific newsletter views', function (): void {
    $this->tenant->slug = 'thyroid-ghana-foundation';

    expect(app(CmsViewResolver::class)->resolve('newsletters.archive'))
        ->toBe('site-thyroid-ghana-foundation::public.newsletters.archive');
});

it('does not publish internal or draft newsletters', function (): void {
    foreach ([['audience' => 'internal_only', 'status' => 'published'], ['audience' => 'public', 'status' => 'draft']] as $attributes) {
        $post = PostFactory::new()->forTenant((string) $this->tenant->id)->create($attributes + [
            'post_type_id' => $this->type->id,
            'published_at' => now(),
        ]);

        $this->get("http://{$this->host}/site/newsletters", ['HTTP_HOST' => $this->host])
            ->assertOk()->assertDontSee($post->title);
        $this->get("http://{$this->host}/site/newsletters/{$post->slug}", ['HTTP_HOST' => $this->host])
            ->assertNotFound();
    }
});
