<?php

declare(strict_types=1);

use App\Modules\CmsCore\Models\Post;
use App\Modules\CmsCore\Models\PostMeta;
use App\Modules\CmsCore\Models\PostType;
use App\Modules\SiteThyroidGhanaFoundation\Database\Seeders\ThyroidSitePagesSeeder;

require_once __DIR__.'/SiteTenantHelper.php';

function tgfEvent(string $title, string $startsOn, array $meta = []): Post
{
    $tenant = app(App\Services\Tenancy\TenantContext::class)->getTenant();
    $type = PostType::query()->where('tenant_id', $tenant->id)->where('slug', 'event')->firstOrFail();
    $post = Post::query()->create(['tenant_id' => $tenant->id, 'post_type_id' => $type->id, 'title' => $title, 'slug' => str($title)->slug()->toString(), 'body' => '', 'excerpt' => 'About '.$title, 'status' => 'published', 'published_at' => now()->subDay(), 'author_id' => (string) Post::query()->value('author_id')]);
    foreach (['starts_on' => $startsOn] + $meta as $key => $value) {
        PostMeta::query()->create(['tenant_id' => $tenant->id, 'post_id' => $post->id, 'key' => $key, 'value' => $value]);
    }

    return $post;
}

describe('Site pages seeder', function (): void {
    test('publishes Contact without a form, adds no legal pages and never overwrites edits', function (): void {
        tgfSiteTenant();
        app(ThyroidSitePagesSeeder::class)->run();

        expect(Post::query()->where('slug', 'contact')->value('status'))->toBe('published')
            ->and(PostMeta::query()->where('key', 'enquiry_form')->whereIn('post_id', Post::query()->where('slug', 'contact')->select('id'))->exists())->toBeFalse()
            ->and(Post::query()->whereIn('slug', ['privacy-policy', 'terms-of-use', 'medical-disclaimer'])->exists())->toBeFalse();

        Post::query()->where('slug', 'contact')->update(['body' => '<p>Edited by the foundation</p>']);
        app(ThyroidSitePagesSeeder::class)->run();

        expect(Post::query()->where('slug', 'contact')->count())->toBe(1)
            ->and(Post::query()->where('slug', 'contact')->value('body'))->toBe('<p>Edited by the foundation</p>');
    });
});

describe('Contact page', function (): void {
    test('shows the details and email links without a form', function (): void {
        tgfSiteTenant();
        app(ThyroidSitePagesSeeder::class)->run();

        $this->get(TGF_SITE.'/contact')->assertOk()->assertSee('Get in touch')->assertSee('href="tel:&#43;233243376304"', false)
            ->assertSee('href="mailto:info&#64;thyroidghanafoundation.org"', false)->assertDontSee('name="message"', false)->assertDontSee('<form', false);
    });
});

describe('Legal pages supplied by the foundation', function (): void {
    test('are linked in the footer once the foundation publishes them', function (): void {
        $tenant = tgfSiteTenant();
        app(ThyroidSitePagesSeeder::class)->run();

        $this->get(TGF_SITE.'/privacy-policy')->assertNotFound();
        expect($this->get(TGF_SITE.'/contact')->getContent())->not->toContain('Privacy Policy');

        $type = PostType::query()->where('tenant_id', $tenant->id)->where('slug', 'page')->firstOrFail();
        Post::query()->create(['tenant_id' => $tenant->id, 'post_type_id' => $type->id, 'title' => 'Privacy Policy', 'slug' => 'privacy-policy', 'body' => '<h2>Who we are</h2>', 'status' => 'published', 'published_at' => now(), 'author_id' => (string) Post::query()->value('author_id')]);

        $this->get(TGF_SITE.'/privacy-policy')->assertOk()->assertSee('Who we are');
        expect($this->get(TGF_SITE.'/contact')->getContent())->toContain('Privacy Policy')->not->toContain('Terms of Use');
    });
});

describe('Events page', function (): void {
    test('says so when nothing is scheduled', function (): void {
        tgfSiteTenant();
        app(ThyroidSitePagesSeeder::class)->run();

        $this->get(TGF_SITE.'/events')->assertOk()->assertSee('No events are scheduled right now');
    });

    test('lists upcoming events soonest first and past events separately', function (): void {
        tgfSiteTenant();
        app(ThyroidSitePagesSeeder::class)->run();
        tgfEvent('Later screening', today()->addDays(30)->toDateString(), ['location' => 'Kumasi']);
        tgfEvent('Sooner forum', today()->addDays(5)->toDateString());
        tgfEvent('Old camp', today()->subDays(40)->toDateString());
        tgfEvent('Broken date', 'soon');

        $html = $this->get(TGF_SITE.'/events')->assertOk()->assertDontSee('No events are scheduled')->getContent();

        expect($html)->toContain('Kumasi')->not->toContain('Broken date')
            ->and(mb_strpos($html, 'Sooner forum'))->toBeLessThan(mb_strpos($html, 'Later screening'))
            ->and(mb_strpos($html, 'Later screening'))->toBeLessThan(mb_strpos($html, 'Old camp'))
            ->and($html)->toContain('Past events');
    });

    test('shows a register button only for upcoming events with a safe web address', function (): void {
        tgfSiteTenant();
        app(ThyroidSitePagesSeeder::class)->run();
        tgfEvent('Open day', today()->addDays(3)->toDateString(), ['register_url' => 'https://forms.example.com/open-day']);
        tgfEvent('Sneaky day', today()->addDays(4)->toDateString(), ['register_url' => 'javascript:alert(1)']);
        tgfEvent('Finished day', today()->subDays(3)->toDateString(), ['register_url' => 'https://forms.example.com/finished']);

        $html = $this->get(TGF_SITE.'/events')->assertOk()->getContent();

        expect($html)->toContain('https://forms.example.com/open-day')->not->toContain('javascript:alert')->not->toContain('forms.example.com/finished');
    });
});
