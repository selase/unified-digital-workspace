<?php

declare(strict_types=1);

use App\Modules\CmsCore\Models\Post;
use App\Modules\CmsCore\Models\PostMeta;
use App\Modules\CmsCore\Models\PostType;
use App\Modules\CmsCore\Support\EnquiryToken;
use App\Modules\SiteThyroidGhanaFoundation\Database\Seeders\ThyroidSitePagesSeeder;
use Illuminate\Support\Facades\Mail;

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
    test('publishes Contact, keeps the legal pages as drafts and never overwrites edits', function (): void {
        tgfSiteTenant();
        app(ThyroidSitePagesSeeder::class)->run();

        expect(Post::query()->where('slug', 'contact')->value('status'))->toBe('published');
        foreach (['privacy-policy', 'terms-of-use', 'medical-disclaimer'] as $slug) {
            expect(Post::query()->where('slug', $slug)->value('status'))->toBe('draft');
        }

        Post::query()->where('slug', 'privacy-policy')->update(['body' => '<p>Lawyer approved text</p>']);
        app(ThyroidSitePagesSeeder::class)->run();

        expect(Post::query()->where('slug', 'privacy-policy')->count())->toBe(1)
            ->and(Post::query()->where('slug', 'privacy-policy')->value('body'))->toBe('<p>Lawyer approved text</p>');
    });
});

describe('Contact page', function (): void {
    test('shows the details and a message form that reaches the foundation', function (): void {
        tgfSiteTenant();
        app(ThyroidSitePagesSeeder::class)->run();
        Mail::fake();

        $this->get(TGF_SITE.'/contact')->assertOk()->assertSee('Get in touch')->assertSee('href="tel:&#43;233243376304"', false)->assertSee('href="mailto:info&#64;thyroidghanafoundation.org"', false)->assertSee('name="message"', false);

        $this->postJson(TGF_SITE.'/enquiries', ['type' => 'general', 'name' => 'Kofi Visitor', 'email' => 'kofi@example.com', 'message' => 'Where is your next screening day?', 'form_token' => EnquiryToken::issue(time() - 10), 'website' => ''])->assertSuccessful();
    });
});

describe('Legal pages', function (): void {
    test('are hidden while drafts and linked in the footer once published', function (): void {
        tgfSiteTenant();
        app(ThyroidSitePagesSeeder::class)->run();

        $this->get(TGF_SITE.'/privacy-policy')->assertNotFound();
        expect($this->get(TGF_SITE.'/contact')->getContent())->not->toContain('Privacy Policy');

        Post::query()->where('slug', 'privacy-policy')->update(['status' => 'published', 'published_at' => now()]);

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
