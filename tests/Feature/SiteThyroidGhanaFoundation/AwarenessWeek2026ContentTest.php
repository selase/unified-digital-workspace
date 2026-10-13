<?php

declare(strict_types=1);

use App\Models\TenantModule;
use App\Models\User;
use App\Modules\CmsCore\Mail\SiteEnquiryReceived;
use App\Modules\CmsCore\Models\Media;
use App\Modules\CmsCore\Models\Post;
use App\Modules\CmsCore\Support\EnquiryToken;
use App\Modules\SiteThyroidGhanaFoundation\Database\Seeders\ThyroidAwarenessWeek2026GallerySeeder;
use App\Modules\SiteThyroidGhanaFoundation\Database\Seeders\ThyroidAwarenessWeek2026Seeder;
use App\Modules\SiteThyroidGhanaFoundation\Database\Seeders\ThyroidContactLinksSeeder;
use App\Modules\SiteThyroidGhanaFoundation\Database\Seeders\ThyroidFounderDetailsSeeder;
use App\Modules\SiteThyroidGhanaFoundation\Database\Seeders\ThyroidGhanaSeeder;
use Illuminate\Support\Facades\Mail;
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
    Storage::disk('tenant')->deleteDirectory('cms/gallery/awareness-week-2026');
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

    expect($html)->toContain('rgba(15,23,42,0.35)')->toContain('90% 30%/cover');
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

it('shows portrait photos in the story as a centred card instead of full width', function (): void {
    $story = Post::query()->where('slug', ThyroidAwarenessWeek2026Seeder::STORY_SLUG)->firstOrFail();

    expect($story->body)
        ->toContain('<figure class="mx-auto max-w-md"><img src="/cms-media/')
        ->toContain('<figure><img src="/cms-media/');
});

it('replaces the placeholder photo tiles on the media gallery page with the event gallery', function (): void {
    $html = $this->get('https://thyroidghanafoundation.org/media-gallery')->assertOk()->getContent();

    expect(mb_substr_count($html, 'aria-label="Open photo:'))->toBe(29)
        ->and($html)->toContain('id="awareness-week-2026"')
        ->toContain('International Thyroid Awareness Week 2026')
        ->toContain('Read the story')
        ->not->toContain('World Thyroid Day 2023')
        ->not->toContain('Fundraising Gala')
        ->not->toContain('linear-gradient(135deg, #0D9488, #134E4A)');
});

it('keeps the gallery ordered, captioned and safe to re-seed', function (): void {
    app(ThyroidAwarenessWeek2026GallerySeeder::class)->run();

    $photos = Media::query()->where('metadata->gallery', ThyroidAwarenessWeek2026GallerySeeder::GALLERY)->get()
        ->sortBy(fn (Media $photo): int => (int) $photo->metadata['order'])->values();

    expect($photos)->toHaveCount(29)
        ->and($photos->pluck('metadata.order')->all())->toBe(range(1, 29))
        ->and($photos->first()->path)->toBe('cms/news/awareness-week-2026/vip-group-photo.jpg')
        ->and($photos->every(fn (Media $photo): bool => filled($photo->metadata['caption']) && filled($photo->alt_text)))->toBeTrue()
        ->and(Media::query()->where('path', 'like', 'cms/gallery/awareness-week-2026/%')->count())->toBe(15);
});

it('credits stories to the organisation instead of the account that entered them', function (): void {
    $author = Post::query()->where('slug', ThyroidAwarenessWeek2026Seeder::STORY_SLUG)->firstOrFail()->author;

    $this->get('https://thyroidghanafoundation.org/news/'.ThyroidAwarenessWeek2026Seeder::STORY_SLUG)
        ->assertOk()
        ->assertSee('<span>Thyroid Ghana Foundation</span>', false)
        ->assertDontSee(mb_trim($author->first_name.' '.$author->last_name));
});

it('serves the favicon from a path the web server does not intercept, on the custom domain', function (): void {
    $response = $this->get('https://thyroidghanafoundation.org/site-icon/favicon.ico');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Content-Type'))->toContain('icon');
});

it('makes the email address and phone number on the action pages tappable', function (): void {
    foreach (['donate' => 'Donation%20enquiry', 'volunteer' => 'Volunteer%20enquiry', 'membership' => 'Membership%20enquiry', 'partner-us' => 'Partnership%20enquiry'] as $slug => $subject) {
        // The sanitizer entity-encodes characters such as @ and +; browsers decode them.
        $html = html_entity_decode($this->get('https://thyroidghanafoundation.org/'.$slug)->assertOk()->getContent());

        expect($html)->toContain('mailto:info@thyroidghanafoundation.org?subject='.$subject);
    }

    expect(html_entity_decode($this->get('https://thyroidghanafoundation.org/donate')->getContent()))->toContain('href="tel:+233243376304"');
});

it('links contact details once and never inside an existing link', function (): void {
    $html = '<p>Email info@thyroidghanafoundation.org or call +233 (024) 337 6304.</p>'
        .'<p><a href="https://example.test">info@thyroidghanafoundation.org</a></p>';

    $linked = ThyroidContactLinksSeeder::link($html, 'Test');

    expect(mb_substr_count($linked, 'mailto:'))->toBe(1)
        ->and(mb_substr_count($linked, 'tel:+233243376304'))->toBe(1)
        ->and(ThyroidContactLinksSeeder::link($linked, 'Test'))->toBe($linked);
});

it('puts the right email form on each action page and nowhere else', function (): void {
    foreach (['volunteer' => 'volunteer', 'membership' => 'membership', 'partner-us' => 'partnership', 'donate' => 'donation'] as $slug => $type) {
        $this->get('https://thyroidghanafoundation.org/'.$slug)
            ->assertOk()
            ->assertSee('id="enquiry"', false)
            ->assertSee('name="type" value="'.$type.'"', false);
    }

    $this->get('https://thyroidghanafoundation.org/partner-us')->assertSee('name="organisation"', false);
    $this->get('https://thyroidghanafoundation.org/volunteer')->assertDontSee('name="organisation"', false);

    foreach (['about', 'the-founder', 'faq'] as $slug) {
        $this->get('https://thyroidghanafoundation.org/'.$slug)->assertOk()->assertDontSee('id="enquiry"', false);
    }
});

it('emails enquiries from the foundation site to the foundation inbox', function (): void {
    Mail::fake();

    $this->postJson('https://thyroidghanafoundation.org/enquiries', [
        'type' => 'membership',
        'name' => 'Ama Boateng',
        'email' => 'ama@example.test',
        'message' => 'I live with Hashimoto and would like to join the community.',
        'form_token' => EnquiryToken::issue(time() - 10),
    ])->assertOk();

    Mail::assertSent(SiteEnquiryReceived::class, fn (SiteEnquiryReceived $mail): bool => $mail->hasTo('info@thyroidghanafoundation.org')
        && $mail->hasReplyTo('ama@example.test')
        && $mail->envelope()->subject === '[Thyroid Ghana Foundation] Membership enquiry from Ama Boateng');
});
