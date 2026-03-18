<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Database\Seeders;

use App\Models\Tenant;
use App\Modules\CmsCore\Models\Category;
use App\Modules\CmsCore\Models\Menu;
use App\Modules\CmsCore\Models\MenuItem;
use App\Modules\CmsCore\Models\Post;
use App\Modules\CmsCore\Models\PostType;
use App\Modules\CmsCore\Models\Setting;
use App\Modules\CmsCore\Models\Tag;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Database\Seeder;

final class ThyroidGhanaSeeder extends Seeder
{
    private string $authorId;

    public function run(): void
    {
        $tenant = Tenant::where('slug', 'thyroid-ghana-foundation')->firstOrFail();

        app(TenantContext::class)->setTenant($tenant);
        app(TenantDatabaseManager::class)->configureShared();
        setPermissionsTeamId($tenant->id);

        // Use the first associated user as author, or the superadmin
        $user = $tenant->users()->first()
            ?? \App\Models\User::where('email', 'hiselase@gmail.com')->first();
        $this->authorId = (string) ($user?->uuid ?? '');

        $this->seedPostTypes($tenant);
        $this->seedCategories($tenant);
        $this->seedTags($tenant);
        $this->seedPages($tenant);
        $this->seedNews($tenant);
        $this->seedMenus($tenant);
        $this->seedThemeSettings($tenant);
    }

    private function seedPostTypes(Tenant $tenant): void
    {
        PostType::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'page'],
            ['name' => 'Page', 'description' => 'Static website pages', 'is_active' => true, 'supports' => ['excerpt', 'featured_media', 'parent']]
        );

        PostType::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'post'],
            ['name' => 'News', 'description' => 'News articles and announcements', 'is_active' => true, 'supports' => ['excerpt', 'featured_media', 'categories', 'tags']]
        );
    }

    private function seedCategories(Tenant $tenant): void
    {
        $categories = ['Awareness', 'Patient Support', 'Research', 'Events', 'Partnerships'];

        foreach ($categories as $name) {
            Category::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => \Illuminate\Support\Str::slug($name)],
                ['name' => $name, 'is_active' => true, 'sort_order' => 0]
            );
        }
    }

    private function seedTags(Tenant $tenant): void
    {
        $tags = ['thyroid', 'health', 'ghana', 'awareness-week', 'surgery', 'fundraising', 'west-africa'];

        foreach ($tags as $tag) {
            Tag::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $tag],
                ['name' => ucwords(str_replace('-', ' ', $tag))]
            );
        }
    }

    private function seedPages(Tenant $tenant): void
    {
        $pageType = PostType::where('tenant_id', $tenant->id)->where('slug', 'page')->first();

        $pages = [
            [
                'slug' => 'home',
                'title' => 'Home',
                'excerpt' => 'Creating awareness of thyroid diseases in Ghana',
                'body' => '<p>Welcome to the Thyroid Ghana Foundation. We are dedicated to raising awareness about thyroid disorders and ensuring prompt, appropriate treatment access for affected Ghanaians.</p>',
                'status' => 'published',
                'published_at' => now(),
            ],
            [
                'slug' => 'about',
                'title' => 'About Us',
                'excerpt' => 'Learn about our mission, vision, and the work we do across Ghana.',
                'body' => '<h2>Our Mission</h2><p>The Thyroid Ghana Foundation is a non-governmental organization dedicated to creating awareness of thyroid diseases in Ghana, creating opportunities for early detection of thyroid problems, supporting thyroid research and institutions involved in thyroid disease management, providing access to affordable treatment and advocating for improved healthcare practices for thyroid disease patients in the Country.</p><h2>Our Vision</h2><p>A Ghana where thyroid diseases are detected early, treated effectively, and no patient is left behind due to lack of awareness or access to healthcare.</p><h2>Our History</h2><p>Founded in July 2018, the Thyroid Ghana Foundation has grown from a small awareness initiative into a nationally recognized organization. As a proud member of the Thyroid Federation International, we connect Ghanaian patients and healthcare providers with global best practices in thyroid disease management.</p><p>Our work spans across multiple regions in Ghana, reaching communities that previously had little to no access to thyroid health information or services.</p>',
                'status' => 'published',
                'published_at' => now(),
            ],
            [
                'slug' => 'the-challenge',
                'title' => 'The Challenge',
                'excerpt' => 'Understanding the scale of thyroid disease in Ghana.',
                'body' => '<h2>The Thyroid Health Challenge in Ghana</h2><p>Thyroid disorders affect millions of people worldwide, yet awareness in Ghana remains critically low. Many patients live with undiagnosed conditions for years, suffering from symptoms that significantly impact their quality of life.</p><h2>Key Challenges</h2><ul><li><strong>Low Awareness:</strong> Many Ghanaians are unaware of thyroid diseases, their symptoms, and the importance of early detection.</li><li><strong>Limited Access:</strong> Access to specialized thyroid care is concentrated in major urban centers, leaving rural communities underserved.</li><li><strong>Cost Barriers:</strong> The cost of diagnosis, medication, and surgery can be prohibitive for many families.</li><li><strong>Specialist Shortage:</strong> There is a significant shortage of endocrinologists and thyroid specialists across the country.</li></ul><p>The Thyroid Ghana Foundation exists to address these challenges head-on, working at every level from community awareness to specialist referral networks.</p>',
                'status' => 'published',
                'published_at' => now(),
            ],
            [
                'slug' => 'our-team',
                'title' => 'Our Team',
                'excerpt' => 'Meet the dedicated team behind the Thyroid Ghana Foundation.',
                'body' => '<h2>Leadership Team</h2><p>Our foundation is led by a passionate team of healthcare professionals, advocates, and community leaders committed to improving thyroid health outcomes in Ghana.</p><h3>Nana Adwoa Konadu Dsane</h3><p><strong>Founder & President</strong></p><p>Nana Adwoa Konadu Dsane founded the Thyroid Ghana Foundation in 2018 driven by a deep commitment to improving healthcare access for thyroid patients. Under her leadership, the foundation has grown into a nationally recognized advocate for thyroid health.</p><h3>Rev. Prof. Patrick F. Ayeh-Kumi</h3><p><strong>Management Board Chair</strong></p><p>A distinguished academic and researcher who brings decades of medical expertise to the foundation\'s governance and strategic direction.</p><h3>Mr. Leslie Chartey Kumahlor</h3><p><strong>Head of Operations</strong></p><p>Oversees the day-to-day operations and ensures our programs reach communities across Ghana effectively.</p><h3>Dr. Joyce Emefa Addo-Klah</h3><p><strong>Public Relations Officer</strong></p><p>Leads our communications strategy and public engagement initiatives.</p><h3>Frank Anyimadu</h3><p><strong>Consultant Dietician</strong></p><p>Provides nutritional guidance and develops dietary programs for thyroid patients.</p><h3>Justice Kwesi Baah</h3><p><strong>Research Coordinator</strong></p><p>Coordinates research initiatives and partnerships with academic institutions.</p>',
                'status' => 'published',
                'published_at' => now(),
            ],
            [
                'slug' => 'donate',
                'title' => 'Support Our Cause',
                'excerpt' => 'Your donation helps us reach more patients and fund critical thyroid research.',
                'body' => '<h2>Make a Difference Today</h2><p>Your generous contribution directly supports our mission to improve thyroid health outcomes across Ghana. Every donation helps us:</p><ul><li>Fund screening programs in underserved communities</li><li>Provide medication subsidies for patients who cannot afford treatment</li><li>Support surgical interventions for thyroid cancer patients</li><li>Run awareness campaigns during World Thyroid Awareness Week</li><li>Train healthcare workers in thyroid disease detection</li></ul><h2>How to Donate</h2><p>We accept donations through mobile money, bank transfer, and online payment. Contact us at <strong>+233 (024) 337 6304</strong> or email <strong>info@thyroidghanafoundation.org</strong> for donation details.</p><h2>Corporate Partnerships</h2><p>We welcome corporate partnerships and sponsorships. Partner with us to make a lasting impact on thyroid health in Ghana.</p>',
                'status' => 'published',
                'published_at' => now(),
            ],
            [
                'slug' => 'volunteer',
                'title' => 'Volunteer With Us',
                'excerpt' => 'Join our team of volunteers making a difference in thyroid health.',
                'body' => '<h2>Become a Volunteer</h2><p>We are always looking for passionate individuals to join our cause. Volunteers play a vital role in our community outreach, awareness campaigns, and patient support programs.</p><h2>Volunteer Opportunities</h2><ul><li><strong>Community Health Educators:</strong> Help spread awareness about thyroid diseases in your community.</li><li><strong>Event Coordinators:</strong> Assist in organizing awareness events, screenings, and fundraisers.</li><li><strong>Patient Support:</strong> Provide emotional support and guidance to thyroid patients and their families.</li><li><strong>Research Assistants:</strong> Support our research initiatives and data collection efforts.</li></ul><p>To volunteer, contact us at <strong>info@thyroidghanafoundation.org</strong> or call <strong>+233 (024) 337 6304</strong>.</p>',
                'status' => 'published',
                'published_at' => now(),
            ],
            [
                'slug' => 'partner-us',
                'title' => 'Partner With Us',
                'excerpt' => 'Explore partnership opportunities with the Thyroid Ghana Foundation.',
                'body' => '<h2>Partnership Opportunities</h2><p>We believe in the power of collaboration. By partnering with us, your organization can make a meaningful contribution to thyroid health in Ghana while fulfilling corporate social responsibility goals.</p><h2>Types of Partnerships</h2><ul><li><strong>Healthcare Partnerships:</strong> Collaborate on screening programs, specialist referral networks, and treatment subsidies.</li><li><strong>Research Partnerships:</strong> Fund or co-lead thyroid research initiatives.</li><li><strong>Corporate Sponsorships:</strong> Sponsor our annual awareness events and community programs.</li><li><strong>Media Partnerships:</strong> Help us amplify our message through media channels.</li></ul><p>Contact us to discuss how we can work together.</p>',
                'status' => 'published',
                'published_at' => now(),
            ],
            [
                'slug' => 'membership',
                'title' => 'Membership',
                'excerpt' => 'Join the Thyroid Ghana Foundation community.',
                'body' => '<h2>Become a Member</h2><p>Membership in the Thyroid Ghana Foundation connects you with a community of patients, healthcare professionals, and advocates committed to improving thyroid health outcomes in Ghana.</p><h2>Member Benefits</h2><ul><li>Access to educational resources and workshops</li><li>Invitation to annual conferences and events</li><li>Connection with thyroid specialists and support groups</li><li>Regular newsletters with the latest thyroid health information</li><li>Opportunity to participate in research studies</li></ul><p>For membership inquiries, please contact us at <strong>info@thyroidghanafoundation.org</strong>.</p>',
                'status' => 'published',
                'published_at' => now(),
            ],
        ];

        foreach ($pages as $pageData) {
            Post::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'post_type_id' => $pageType->id, 'slug' => $pageData['slug']],
                array_merge($pageData, ['post_type_id' => $pageType->id, 'author_id' => $this->authorId])
            );
        }
    }

    private function seedNews(Tenant $tenant): void
    {
        $newsType = PostType::where('tenant_id', $tenant->id)->where('slug', 'post')->first();
        $awarenessCategory = Category::where('tenant_id', $tenant->id)->where('slug', 'awareness')->first();

        $articles = [
            [
                'slug' => 'ghana-health-awards-honors-launched',
                'title' => 'Ghana Health Awards and Honors Launched',
                'excerpt' => 'The Thyroid Ghana Foundation participates in the inaugural Ghana Health Awards ceremony recognizing outstanding contributions to healthcare.',
                'body' => '<p>The Thyroid Ghana Foundation was honored to participate in the launch of the Ghana Health Awards and Honors in December 2022. This prestigious initiative recognizes outstanding contributions to healthcare delivery across Ghana.</p><p>Our foundation was acknowledged for our tireless work in thyroid health awareness and patient support. The event brought together healthcare leaders, policy makers, and organizations committed to improving health outcomes in the country.</p><p>We remain committed to advancing thyroid health and are grateful for this recognition of our efforts.</p>',
                'status' => 'published',
                'published_at' => now()->subMonths(3),
            ],
            [
                'slug' => 'world-thyroid-awareness-week-2024',
                'title' => '14th World Thyroid Awareness Week Launch',
                'excerpt' => 'The foundation leads Ghana\'s participation in the annual World Thyroid Awareness Week with community screenings and education.',
                'body' => '<p>The Thyroid Ghana Foundation proudly launched the 14th World Thyroid Awareness Week in Ghana, bringing together healthcare professionals, patients, and community leaders for a week of education and screening.</p><p>Activities included free thyroid screenings at community health centers, educational workshops for healthcare workers, and public awareness campaigns across social media and traditional media platforms.</p><p>World Thyroid Awareness Week is observed globally to raise awareness about thyroid diseases and the importance of early detection and treatment.</p>',
                'status' => 'published',
                'published_at' => now()->subMonths(2),
            ],
            [
                'slug' => 'expanding-patient-forums-to-northern-regions',
                'title' => 'Expanding Patient Forums to Northern Regions',
                'excerpt' => 'Our patient support forums now reach communities in the Northern, Upper East, and Upper West regions.',
                'body' => '<p>We are thrilled to announce the expansion of our Patient Forums program to the Northern, Upper East, and Upper West regions of Ghana. This expansion means that thyroid patients in these historically underserved areas now have access to support groups and educational resources.</p><p>The forums provide a safe space for patients to share experiences, learn about their conditions, and receive guidance from trained community health educators. Each forum meets monthly and is supported by healthcare professionals who volunteer their time.</p><p>This expansion was made possible through generous donations from our supporters and partnerships with regional health directorates.</p>',
                'status' => 'published',
                'published_at' => now()->subMonth(),
            ],
        ];

        foreach ($articles as $article) {
            $post = Post::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'post_type_id' => $newsType->id, 'slug' => $article['slug']],
                array_merge($article, ['post_type_id' => $newsType->id, 'author_id' => $this->authorId])
            );

            if ($awarenessCategory && $post->wasRecentlyCreated) {
                $post->categories()->syncWithoutDetaching([$awarenessCategory->id => ['sort_order' => 0]]);
            }
        }
    }

    private function seedMenus(Tenant $tenant): void
    {
        $menu = Menu::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'main-navigation'],
            ['name' => 'Main Navigation']
        );

        if ($menu->items()->count() > 0) {
            return;
        }

        $pageType = PostType::where('tenant_id', $tenant->id)->where('slug', 'page')->first();

        $items = [
            ['label' => 'Home', 'slug' => 'home', 'sort_order' => 0],
            ['label' => 'About', 'slug' => 'about', 'sort_order' => 1],
            ['label' => 'The Challenge', 'slug' => 'the-challenge', 'sort_order' => 2],
            ['label' => 'Our Team', 'slug' => 'our-team', 'sort_order' => 3],
            ['label' => 'News', 'slug' => null, 'sort_order' => 4, 'url' => '/site/news'],
            ['label' => 'Get Involved', 'slug' => 'volunteer', 'sort_order' => 5],
        ];

        foreach ($items as $itemData) {
            $postId = null;
            if ($itemData['slug']) {
                $post = Post::where('tenant_id', $tenant->id)
                    ->where('post_type_id', $pageType->id)
                    ->where('slug', $itemData['slug'])
                    ->first();
                $postId = $post?->id;
            }

            MenuItem::query()->create([
                'tenant_id' => $tenant->id,
                'menu_id' => $menu->id,
                'label' => $itemData['label'],
                'url' => $itemData['url'] ?? null,
                'post_id' => $postId,
                'sort_order' => $itemData['sort_order'],
            ]);
        }
    }

    private function seedThemeSettings(Tenant $tenant): void
    {
        $settings = [
            'site_name' => 'Thyroid Ghana Foundation',
            'site_tagline' => 'Advancing Thyroid Health Across Ghana',
            'primary_color' => '#0D9488',
            'secondary_color' => '#B45309',
            'footer_text' => '© ' . date('Y') . ' Thyroid Ghana Foundation. All rights reserved. Member of Thyroid Federation International.',
            'active_theme' => 'default',
        ];

        foreach ($settings as $key => $value) {
            Setting::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'group' => 'theme', 'key' => $key],
                ['value' => $value]
            );
        }
    }
}
