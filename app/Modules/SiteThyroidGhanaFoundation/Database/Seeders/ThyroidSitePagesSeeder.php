<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\CmsCore\Models\Post;
use App\Modules\CmsCore\Models\PostType;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Database\Seeder;

/**
 * Adds the Contact page and the "event" post type behind the Events page.
 *
 * Contact only restates details already in the site footer. Legal pages (privacy, terms, medical disclaimer) are
 * supplied by the foundation and published under the slugs privacy-policy, terms-of-use and medical-disclaimer,
 * which the footer links automatically. Existing pages are never overwritten, so CMS edits survive a re-run.
 */
final class ThyroidSitePagesSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'thyroid-ghana-foundation')->firstOrFail();

        app(TenantContext::class)->setTenant($tenant);
        app(TenantDatabaseManager::class)->configureShared();
        setPermissionsTeamId($tenant->id);

        PostType::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'event'],
            ['name' => 'Event', 'description' => 'Dated events shown on the Events page. Set the "starts_on" meta (Y-m-d); "location" and "register_url" are optional.', 'is_active' => true, 'supports' => ['excerpt', 'featured_media']],
        );

        $page = PostType::query()->where('tenant_id', $tenant->id)->where('slug', 'page')->firstOrFail();
        $author = (string) ($tenant->users()->first() ?? User::query()->where('email', 'hiselase@gmail.com')->first())?->uuid;

        foreach ($this->pages() as $data) {
            Post::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'post_type_id' => $page->id, 'slug' => $data['slug']],
                ['title' => $data['title'], 'excerpt' => $data['excerpt'], 'body' => $data['body'], 'status' => $data['status'], 'published_at' => $data['status'] === 'published' ? now() : null, 'author_id' => $author],
            );

        }
    }

    /**
     * @return list<array{slug: string, title: string, excerpt: string, body: string, status: string}>
     */
    private function pages(): array
    {
        return [
            [
                'slug' => 'contact',
                'title' => 'Contact Us',
                'excerpt' => 'Call, email or visit us.',
                'status' => 'published',
                'body' => '<h2>Get in touch</h2><p>We read every message. For urgent medical concerns please see your doctor or visit your nearest health facility.</p><ul><li><strong>Phone:</strong> <a href="tel:+233243376304">+233 (024) 337 6304</a></li><li><strong>Email:</strong> <a href="mailto:info@thyroidghanafoundation.org">info@thyroidghanafoundation.org</a></li><li><strong>Address:</strong> 112, Lane 10, Onyinase Street, Awoshie, Accra, Ghana</li></ul><p><a href="https://www.google.com/maps/search/?api=1&amp;query=112+Lane+10+Onyinase+Street+Awoshie+Accra+Ghana" rel="noopener" target="_blank">Open our address in Google Maps</a></p>',
            ],
        ];
    }
}
