<?php

declare(strict_types=1);

use App\Models\TenantModule;
use App\Models\User;
use App\Modules\CmsCore\Models\Media;
use App\Modules\CmsCore\Models\Post;
use App\Modules\SiteThyroidGhanaFoundation\Database\Seeders\ThyroidAwarenessWeek2026Seeder;
use App\Modules\SiteThyroidGhanaFoundation\Database\Seeders\ThyroidFounderDetailsSeeder;
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

    expect(Media::query()->where('path', 'like', 'cms/news/awareness-week-2026/%')->count())->toBe(16);

    foreach (Media::query()->where('path', 'like', 'cms/news/awareness-week-2026/%')->get() as $media) {
        expect(Storage::disk('tenant')->exists($media->path))->toBeTrue()
            ->and($media->alt_text)->not->toBeEmpty();
    }
});

it('is safe to run again without duplicating anything', function (): void {
    app(ThyroidAwarenessWeek2026Seeder::class)->run();

    expect(Post::query()->where('slug', ThyroidAwarenessWeek2026Seeder::STORY_SLUG)->count())->toBe(1)
        ->and(Post::query()->forType('slider')->count())->toBe(6)
        ->and(Media::query()->where('path', 'like', 'cms/news/awareness-week-2026/%')->count())->toBe(16);
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
        ->toBeLessThan($position('>Know Your Thyroid Health</h2>'))
        ->and($position('>Know Your Thyroid Health</h2>'))
        ->toBeLessThan($position('>Dr. Nana Adwoa Konadu Dsane</h2>'))
        ->and($position('>Dr. Nana Adwoa Konadu Dsane</h2>'))
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

it('writes the founder as Dr. in freshly seeded content', function (): void {
    $text = Post::query()->whereIn('slug', ['about', 'the-founder', 'our-team'])->get()
        ->map(fn (Post $post): string => $post->body.$post->excerpt)
        ->implode(' ');

    expect($text)->toContain('Dr. Nana Adwoa Konadu Dsane')
        ->not->toContain('Mrs. Nana')
        ->not->toContain('Mrs. Dsane')
        ->not->toContain('Dsane (Mrs.)');
});

it('corrects the founder honorific on existing content without touching other titles', function (): void {
    Post::query()->where('slug', 'the-founder')->update([
        'excerpt' => 'Mrs. Nana Adwoa Konadu Dsane — Founder.',
        'body' => '<p>She is pursuing a PhD in Human Resources at the University of South Africa, researching X. Mrs. Dsane founded it. Mrs Nana Adwoa Konadu Dsane spoke. Nana Adwoa Konadu Dsane (Mrs.) leads. '
            .'Reviewed by Dr. Mrs. Josephine Akpalu and Mrs. Beatrice Williams.</p>',
    ]);

    app(ThyroidFounderDetailsSeeder::class)->run();

    $founder = Post::query()->where('slug', 'the-founder')->firstOrFail();

    expect($founder->excerpt)->toBe('Dr. Nana Adwoa Konadu Dsane — Founder.')
        ->and($founder->body)->toContain('Dr. Dsane founded it.')
        ->toContain('Dr. Nana Adwoa Konadu Dsane spoke.')
        ->toContain('Dr. Nana Adwoa Konadu Dsane leads.')
        ->toContain('She holds a PhD in Human Resources from the University of South Africa, where her research examined X.')
        ->not->toContain('pursuing')
        ->toContain('Dr. Mrs. Josephine Akpalu')
        ->toContain('Mrs. Beatrice Williams');
});

it('gives the founder a hero slide with a focal point and a light overlay', function (): void {
    $html = $this->get('https://thyroidghanafoundation.org/')->assertOk()->getContent();

    expect($html)->toContain('rgba(15,23,42,0.2)')->toContain('72% 35%/cover');
});

it('shows the founder portrait on the Founder page and home quote, and Dr. on the team page', function (): void {
    $portrait = Media::query()->where('path', ThyroidAwarenessWeek2026Seeder::FOUNDER_PORTRAIT_PATH)->firstOrFail();
    $portraitUrl = '/cms-media/'.$portrait->uuid;

    expect(Post::query()->where('slug', 'the-founder')->firstOrFail()->featured_media_id)->toBe($portrait->id);

    $this->get('https://thyroidghanafoundation.org/the-founder')
        ->assertOk()
        ->assertSee($portraitUrl, false)
        ->assertSee('mx-auto max-w-sm', false);

    $this->get('https://thyroidghanafoundation.org/our-team')
        ->assertOk()
        ->assertSee('Dr. Nana Adwoa Konadu Dsane')
        ->assertDontSee('(Mrs.)', false);

    $this->get('https://thyroidghanafoundation.org/')
        ->assertOk()
        ->assertSee($portraitUrl, false);
});

it('shows ampersands in slide eyebrows once-escaped, not double-escaped', function (): void {
    $html = $this->get('https://thyroidghanafoundation.org/')->assertOk()->getContent();

    expect($html)
        ->toContain('Screening, Symptoms &amp; Nutrition</p>')
        ->toContain('Awareness &amp; Education</p>')
        ->toContain('Founder &amp; President</p>')
        ->not->toContain('&amp;amp;');
});
