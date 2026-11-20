<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\CmsCore\Models\Post;
use App\Modules\CmsCore\Models\PostMeta;
use App\Modules\CmsCore\Models\PostType;
use App\Modules\CmsCore\Support\EnquiryType;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Database\Seeder;

/**
 * Adds the Contact page, the "event" post type behind the Events page and the three legal pages.
 *
 * Contact is published straight away because it only restates details already in the site footer. The legal
 * pages are created as DRAFTS: the wording is a starting point that the foundation must review (ideally with
 * a lawyer) and publish themselves. Existing pages are never overwritten, so edits made in the CMS survive a re-run.
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
            $post = Post::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'post_type_id' => $page->id, 'slug' => $data['slug']],
                ['title' => $data['title'], 'excerpt' => $data['excerpt'], 'body' => $data['body'], 'status' => $data['status'], 'published_at' => $data['status'] === 'published' ? now() : null, 'author_id' => $author],
            );

            if ($post->wasRecentlyCreated && isset($data['enquiry'])) {
                PostMeta::query()->updateOrCreate(['post_id' => $post->id, 'key' => 'enquiry_form'], ['value' => $data['enquiry'], 'tenant_id' => $tenant->id]);
            }
        }
    }

    /**
     * @return list<array{slug: string, title: string, excerpt: string, body: string, status: string, enquiry?: string}>
     */
    private function pages(): array
    {
        return [
            [
                'slug' => 'contact',
                'title' => 'Contact Us',
                'excerpt' => 'Call, email or visit us, or send a message and we will reply.',
                'status' => 'published',
                'enquiry' => EnquiryType::General->value,
                'body' => '<h2>Get in touch</h2><p>We read every message. For urgent medical concerns please see your doctor or visit your nearest health facility.</p><ul><li><strong>Phone:</strong> <a href="tel:+233243376304">+233 (024) 337 6304</a></li><li><strong>Email:</strong> <a href="mailto:info@thyroidghanafoundation.org">info@thyroidghanafoundation.org</a></li><li><strong>Address:</strong> 112, Lane 10, Onyinase Street, Awoshie, Accra, Ghana</li></ul><p><a href="https://www.google.com/maps/search/?api=1&amp;query=112+Lane+10+Onyinase+Street+Awoshie+Accra+Ghana" rel="noopener" target="_blank">Open our address in Google Maps</a></p><h2>Send us a message</h2><p>Use the form below and we will reply by email.</p>',
            ],
            [
                'slug' => 'privacy-policy',
                'title' => 'Privacy Policy',
                'excerpt' => 'How the Thyroid Ghana Foundation collects, uses and protects your personal information.',
                'status' => 'draft',
                'body' => '<h2>Who we are</h2><p>The Thyroid Ghana Foundation ("we", "us") runs this website and decides how personal information collected on it is used. You can reach us at <a href="mailto:info@thyroidghanafoundation.org">info@thyroidghanafoundation.org</a>.</p><h2>What we collect</h2><ul><li>Details you send us through our forms: your name, email address, phone number, organisation and message.</li><li>Membership and volunteer applications, and the details of gifts you make to us.</li><li>Basic technical information such as pages visited, collected through cookies needed to run the site.</li></ul><h2>How we use it</h2><p>We use your information to reply to you, process memberships, volunteer sign-ups and donations, issue receipts, and keep you informed about our work if you ask us to. We do not sell your information.</p><h2>Who sees it</h2><p>Only our staff and volunteers who need it, and service providers who help us run the site or process payments on our behalf.</p><h2>How long we keep it</h2><p>We keep your information only as long as needed for these purposes or as the law requires.</p><h2>Your rights</h2><p>Under the Data Protection Act, 2012 (Act 843) you may ask to see, correct or delete the personal information we hold about you. Email us to make a request.</p><h2>Changes</h2><p>We may update this policy and will post the new version on this page.</p>',
            ],
            [
                'slug' => 'terms-of-use',
                'title' => 'Terms of Use',
                'excerpt' => 'The terms that apply when you use the Thyroid Ghana Foundation website.',
                'status' => 'draft',
                'body' => '<h2>Using this website</h2><p>By using this website you agree to these terms. Please use it lawfully and do not try to interfere with its operation or security.</p><h2>Our content</h2><p>The text, images and other material on this site belong to the Thyroid Ghana Foundation or the people who supplied them. You may share pages and quote short extracts with credit to us, but please ask before reusing photographs or republishing articles.</p><h2>Donations and memberships</h2><p>Gifts and membership fees support our work. Please contact us if you believe a payment was made in error.</p><h2>Links to other sites</h2><p>We link to other websites for your convenience and are not responsible for their content.</p><h2>Changes and contact</h2><p>We may update these terms at any time. Questions can be sent to <a href="mailto:info@thyroidghanafoundation.org">info@thyroidghanafoundation.org</a>.</p>',
            ],
            [
                'slug' => 'medical-disclaimer',
                'title' => 'Medical Disclaimer',
                'excerpt' => 'Information on this website is for education and does not replace advice from your doctor.',
                'status' => 'draft',
                'body' => '<h2>Education, not medical advice</h2><p>The health information on this website is provided by the Thyroid Ghana Foundation for general education and awareness. It is not a diagnosis and does not replace advice from a qualified doctor.</p><p>Always speak to your doctor or another qualified health worker about your own symptoms, tests and treatment. Do not delay seeking care, or stop or change your medication, because of something you read here.</p><p>If you think you have a medical emergency, go to the nearest health facility straight away.</p>',
            ],
        ];
    }
}
