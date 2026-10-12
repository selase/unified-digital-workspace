<?php

declare(strict_types=1);

use App\Models\TenantModule;
use App\Models\User;
use App\Modules\CmsCore\Models\Media;
use App\Modules\CmsCore\Models\Post;
use App\Modules\SiteThyroidGhanaFoundation\Database\Seeders\ThyroidAwarenessWeek2026Seeder;
use App\Modules\SiteThyroidGhanaFoundation\Database\Seeders\ThyroidGhanaSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $user = User::factory()->create();
    $this->tenant = setActiveTenantForTest($user, [
        'slug' => 'thyroid-ghana-foundation',
        'custom_domain' => 'thyroidghanafoundation.org',
        'custom_domain_status' => 'active',
    ]);

    foreach (['cms-core', 'site-thyroid-ghana-foundation'] as $module) {
        TenantModule::query()->create([
            'tenant_id' => $this->tenant->id,
            'module_slug' => $module,
            'is_enabled' => true,
            'version' => '1.0.0',
        ]);
    }

    app(ThyroidGhanaSeeder::class)->run();
    app(ThyroidAwarenessWeek2026Seeder::class)->run();
});

afterEach(function (): void {
    Storage::disk('tenant')->deleteDirectory('cms/news/awareness-week-2026');
});

it('publishes the story with its photos and featured image', function (): void {
    $story = Post::query()->where('slug', ThyroidAwarenessWeek2026Seeder::STORY_SLUG)->firstOrFail();

    expect($story->status)->toBe('published')
        ->and($story->featuredMedia?->path)->toBe('cms/news/awareness-week-2026/vip-group-photo.jpg')
        ->and($story->categories->pluck('slug')->all())->toContain('awareness')
        ->and($story->body)->toContain('<h2>Opening session</h2>')
        ->toContain('<figure><img src="/cms-media/')
        ->toContain('<figcaption>')
        ->toContain(' alt="')
        ->toContain(' width="')
        ->not->toContain('<script');

    expect(Media::query()->where('path', 'like', 'cms/news/awareness-week-2026/%')->count())->toBe(11);

    foreach (Media::query()->where('path', 'like', 'cms/news/awareness-week-2026/%')->get() as $media) {
        expect(Storage::disk('tenant')->exists($media->path))->toBeTrue()
            ->and($media->alt_text)->not->toBeEmpty();
    }
});

it('is safe to run again without duplicating anything', function (): void {
    app(ThyroidAwarenessWeek2026Seeder::class)->run();

    expect(Post::query()->where('slug', ThyroidAwarenessWeek2026Seeder::STORY_SLUG)->count())->toBe(1)
        ->and(Post::query()->forType('slider')->count())->toBe(5)
        ->and(Media::query()->where('path', 'like', 'cms/news/awareness-week-2026/%')->count())->toBe(11);
});

it('leads the home page hero with the event slides on the custom domain', function (): void {
    $html = $this->get('https://thyroidghanafoundation.org/')->assertOk()->getContent();

    expect($html)
        ->toContain('International Thyroid Awareness Week 2026')
        ->toContain('href="/news/'.ThyroidAwarenessWeek2026Seeder::STORY_SLUG.'"')
        ->not->toContain('href="/site/');

    $position = fn (string $needle): int => (int) mb_strpos($html, $needle);

    expect($position('>International Thyroid Awareness Week 2026</h2>'))
        ->toBeGreaterThan(0)
        ->toBeLessThan($position('>Advancing Thyroid Health Across Ghana</h2>'));
});

it('renders the story page and lists it in the sitemap', function (): void {
    $this->get('https://thyroidghanafoundation.org/news/'.ThyroidAwarenessWeek2026Seeder::STORY_SLUG)
        ->assertOk()
        ->assertSee('Thyroid and Nutrition', false)
        ->assertSee('<figcaption>', false);

    $this->get('https://thyroidghanafoundation.org/sitemap.xml')
        ->assertOk()
        ->assertSee('/news/'.ThyroidAwarenessWeek2026Seeder::STORY_SLUG, false);
});
