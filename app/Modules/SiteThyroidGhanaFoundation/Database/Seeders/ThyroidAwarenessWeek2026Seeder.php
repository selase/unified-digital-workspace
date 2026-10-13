<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Database\Seeders;

use App\Models\Tenant;
use App\Modules\CmsCore\Models\Category;
use App\Modules\CmsCore\Models\Media;
use App\Modules\CmsCore\Models\Post;
use App\Modules\CmsCore\Models\PostMeta;
use App\Modules\CmsCore\Models\PostType;
use App\Modules\CmsCore\Services\CmsSitemapService;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use App\Services\Tenancy\TenantStorageManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Publishes the International Thyroid Awareness Week 2026 story and two hero
 * slides for the Thyroid Ghana Foundation tenant.
 *
 * Safe to re-run: media, the story and the slides are matched on their
 * stable paths/slugs and updated in place. Existing slides are not touched;
 * the new ones sort ahead of them with negative sort orders.
 *
 *   php artisan db:seed --class="App\Modules\SiteThyroidGhanaFoundation\Database\Seeders\ThyroidAwarenessWeek2026Seeder" --force
 */
final class ThyroidAwarenessWeek2026Seeder extends Seeder
{
    public const STORY_SLUG = 'international-thyroid-awareness-week-2026-thyroid-and-nutrition';

    public const FOUNDER_PORTRAIT_PATH = 'cms/news/awareness-week-2026/founder-portrait.jpg';

    private const MEDIA_DIRECTORY = 'cms/news/awareness-week-2026';

    /**
     * @var array<string, array{title: string, alt: string}>
     */
    private const PHOTOS = [
        'vip-group-photo' => [
            'title' => 'International Thyroid Awareness Week 2026 — VIP group photo',
            'alt' => 'Guests and organisers of International Thyroid Awareness Week 2026 standing together in front of the event backdrop.',
        ],
        'full-house-audience' => [
            'title' => 'International Thyroid Awareness Week 2026 — full-house audience',
            'alt' => 'A packed hall of participants at the Thyroid and Nutrition forum at the University of Ghana Medical Centre.',
        ],
        'opening-session' => [
            'title' => 'International Thyroid Awareness Week 2026 — opening session',
            'alt' => 'Speakers addressing participants during the opening session of the forum.',
        ],
        'nutrition-presentation' => [
            'title' => 'International Thyroid Awareness Week 2026 — nutrition presentation',
            'alt' => 'A presenter speaking at the podium during the Thyroid and Nutrition keynote.',
        ],
        'patient-testimonies' => [
            'title' => 'International Thyroid Awareness Week 2026 — patient testimonies',
            'alt' => 'Patients living with thyroid disorders sharing their experiences on stage.',
        ],
        'expert-panel' => [
            'title' => 'International Thyroid Awareness Week 2026 — expert panel',
            'alt' => 'Health professionals seated on the expert panel answering questions from participants.',
        ],
        'healthy-eating-demonstration' => [
            'title' => 'International Thyroid Awareness Week 2026 — healthy eating demonstration',
            'alt' => 'Dietitians preparing a healthy meal during the live nutrition demonstration.',
        ],
        'healthy-plate' => [
            'title' => 'International Thyroid Awareness Week 2026 — healthy plate',
            'alt' => 'A freshly prepared salad of chickpeas, avocado and vegetables from the nutrition demonstration.',
        ],
        'mds-lancet-partners' => [
            'title' => 'International Thyroid Awareness Week 2026 — MDS-Lancet Laboratories',
            'alt' => 'Representatives of MDS-Lancet Laboratories, a long-standing partner of the Foundation.',
        ],
        'volunteers-team' => [
            'title' => 'International Thyroid Awareness Week 2026 — volunteers and organising team',
            'alt' => 'The volunteers and organising team in matching blue awareness-week t-shirts.',
        ],
        'vip-guests' => [
            'title' => 'International Thyroid Awareness Week 2026 — guests on the red carpet',
            'alt' => 'The Foundation’s founder with two guests on the red carpet in front of the event backdrop.',
        ],
        'thyroid-management-presentation' => [
            'title' => 'International Thyroid Awareness Week 2026 — management of thyroid disorders',
            'alt' => 'A presenter explaining treatment options for thyroid disorders beside the event banners.',
        ],
        'founder-closing-call' => [
            'title' => 'Dr. Nana Adwoa Konadu Dsane — International Thyroid Awareness Week 2026',
            'alt' => 'Dr. Nana Adwoa Konadu Dsane, Founder of Thyroid Ghana Foundation, in an International Thyroid Awareness Week t-shirt.',
        ],
        'founder-portrait' => [
            'title' => 'Dr. Nana Adwoa Konadu Dsane — Founder & President',
            'alt' => 'Portrait of Dr. Nana Adwoa Konadu Dsane, Founder and President of Thyroid Ghana Foundation.',
        ],
        'founder-hero' => [
            'title' => 'Dr. Nana Adwoa Konadu Dsane — hero banner',
            'alt' => 'Dr. Nana Adwoa Konadu Dsane, Founder of Thyroid Ghana Foundation, smiling in an awareness-week t-shirt.',
        ],
        'participants-group-photo' => [
            'title' => 'International Thyroid Awareness Week 2026 — participants group photo',
            'alt' => 'Group photo of participants, speakers and partners after the forum.',
        ],
    ];

    /**
     * @var array<string, Media>
     */
    private array $media = [];

    private string $uploaderId = '';

    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'thyroid-ghana-foundation')->firstOrFail();

        app(TenantContext::class)->setTenant($tenant);
        app(TenantDatabaseManager::class)->configureShared();
        app(TenantStorageManager::class)->configure($tenant);
        setPermissionsTeamId($tenant->id);

        $author = $tenant->users()->first();

        if ($author === null) {
            throw new RuntimeException('The Thyroid Ghana Foundation tenant has no users to attribute the content to.');
        }

        $this->uploaderId = (string) $author->uuid;

        $this->importPhotos();
        $story = $this->publishStory();
        $this->publishSlides($tenant, $story);

        $this->call(ThyroidAwarenessWeek2026GallerySeeder::class);
        $this->call(ThyroidFounderDetailsSeeder::class);
        $this->call(ThyroidContactLinksSeeder::class);
        $this->call(ThyroidEnquiryFormsSeeder::class);

        CmsSitemapService::forget((string) $tenant->id);
    }

    private function importPhotos(): void
    {
        $sourceDirectory = __DIR__.'/assets/awareness-week-2026';
        $disk = Storage::disk('tenant');

        foreach (self::PHOTOS as $name => $details) {
            $sourcePath = "{$sourceDirectory}/{$name}.jpg";
            $storagePath = self::MEDIA_DIRECTORY."/{$name}.jpg";

            if (! is_file($sourcePath)) {
                throw new RuntimeException("Missing bundled photo: {$sourcePath}");
            }

            $disk->put($storagePath, (string) file_get_contents($sourcePath));
            [$width, $height] = (array) getimagesize($sourcePath);

            $this->media[$name] = Media::query()->updateOrCreate(
                ['path' => $storagePath],
                [
                    'disk' => 'tenant',
                    'original_filename' => "{$name}.jpg",
                    'filename' => "{$name}.jpg",
                    'extension' => 'jpg',
                    'mime_type' => 'image/jpeg',
                    'size_bytes' => (int) filesize($sourcePath),
                    'checksum_sha256' => hash_file('sha256', $sourcePath) ?: null,
                    'width' => $width,
                    'height' => $height,
                    'title' => $details['title'],
                    'alt_text' => $details['alt'],
                    'uploaded_by' => $this->uploaderId,
                    'source' => 'upload',
                    'is_public' => true,
                ]
            );
        }
    }

    private function publishStory(): Post
    {
        $postType = PostType::query()->where('slug', 'post')->firstOrFail();

        $story = Post::query()->updateOrCreate(
            ['post_type_id' => $postType->id, 'slug' => self::STORY_SLUG],
            [
                'title' => 'International Thyroid Awareness Week 2026: “Thyroid and Nutrition” Forum at UGMC',
                'excerpt' => 'On 26 May 2026, Thyroid Ghana Foundation and the University of Ghana Medical Centre brought together clinicians, dietitians, patients and the public to explore how nutrition shapes thyroid health.',
                'body' => $this->storyBody(),
                'status' => 'published',
                'published_at' => '2026-06-01 09:00:00',
                'author_id' => $this->uploaderId,
                'featured_media_id' => $this->media['vip-group-photo']->id,
            ]
        );

        $category = Category::query()->where('slug', 'awareness')->first();

        if ($category !== null) {
            $story->categories()->syncWithoutDetaching([$category->id => ['sort_order' => 0]]);
        }

        return $story;
    }

    private function publishSlides(Tenant $tenant, Post $story): void
    {
        $sliderType = PostType::query()->where('slug', 'slider')->firstOrFail();
        $storyUrl = '/site/news/'.$story->slug;

        $slides = [
            [
                'slug' => 'slide-thyroid-awareness-week-2026',
                'title' => 'International Thyroid Awareness Week 2026',
                'body' => 'Thyroid and Nutrition',
                'excerpt' => 'On 26 May 2026, we joined the University of Ghana Medical Centre to bring clinicians, dietitians, patients and the public together around one question: how does food shape thyroid health?',
                'sort_order' => -3,
                'media' => 'vip-group-photo',
                'meta' => [
                    'cta_label' => 'Read the Story',
                    'cta_url' => $storyUrl,
                    'cta2_label' => 'Support Our Cause',
                    'cta2_url' => '/site/donate',
                ],
            ],
            [
                'slug' => 'slide-know-your-thyroid-health',
                'title' => 'Know Your Thyroid Health',
                'body' => 'Screening, Symptoms & Nutrition',
                'excerpt' => 'Patient testimonies, expert panels and live healthy-eating demonstrations — a full house learning how early detection and good nutrition support life with a thyroid condition.',
                'sort_order' => -2,
                'media' => 'full-house-audience',
                'meta' => [
                    'cta_label' => 'See the Highlights',
                    'cta_url' => $storyUrl,
                    'cta2_label' => 'Become an Ambassador',
                    'cta2_url' => '/site/volunteer',
                ],
            ],
            [
                'slug' => 'slide-meet-the-founder',
                'title' => 'Dr. Nana Adwoa Konadu Dsane',
                'body' => 'Founder & President',
                'excerpt' => 'Founded in 2018 after her own battle with hyperthyroidism, Dr. Dsane leads our mission to bring thyroid awareness, early detection and affordable care to every Ghanaian.',
                'sort_order' => -1,
                'media' => 'founder-hero',
                'meta' => [
                    'cta_label' => 'Meet the Founder',
                    'cta_url' => '/site/the-founder',
                    'cta2_label' => 'Support Our Cause',
                    'cta2_url' => '/site/donate',
                    'bg_position' => '90% 30%',
                    'bg_overlay' => '0.35',
                ],
            ],
        ];

        foreach ($slides as $slide) {
            $post = Post::query()->updateOrCreate(
                ['post_type_id' => $sliderType->id, 'slug' => $slide['slug']],
                [
                    'title' => $slide['title'],
                    'body' => $slide['body'],
                    'excerpt' => $slide['excerpt'],
                    'sort_order' => $slide['sort_order'],
                    'status' => 'published',
                    'published_at' => now(),
                    'author_id' => $this->uploaderId,
                    'featured_media_id' => $this->media[$slide['media']]->id,
                ]
            );

            foreach ($slide['meta'] as $key => $value) {
                PostMeta::query()->updateOrCreate(
                    ['post_id' => $post->id, 'key' => $key],
                    ['value' => $value, 'tenant_id' => $tenant->id]
                );
            }
        }
    }

    private function figure(string $photo, string $caption, string $class = ''): string
    {
        $media = $this->media[$photo];
        $url = route('cms-core.media.serve', $media, false);
        $alt = htmlspecialchars((string) $media->alt_text, ENT_QUOTES);
        $caption = htmlspecialchars($caption, ENT_QUOTES);
        $class = $class !== '' ? $class : ((int) $media->height > (int) $media->width ? 'mx-auto max-w-md' : '');
        $classAttribute = $class !== '' ? " class=\"{$class}\"" : '';

        return "<figure{$classAttribute}><img src=\"{$url}\" alt=\"{$alt}\" width=\"{$media->width}\" height=\"{$media->height}\"><figcaption>{$caption}</figcaption></figure>";
    }

    private function storyBody(): string
    {
        $opening = $this->figure('opening-session', 'Speakers addressing participants during the opening session at the University of Ghana Medical Centre.');
        $nutrition = $this->figure('nutrition-presentation', 'The Thyroid and Nutrition keynote presentation.');
        $testimonies = $this->figure('patient-testimonies', 'Patients living with thyroid disorders shared their journeys with the audience.');
        $panel = $this->figure('expert-panel', 'The expert panel took questions on nutrition, medication and screening.');
        $demonstration = $this->figure('healthy-eating-demonstration', 'Dietitians prepared a thyroid-friendly meal live for participants.');
        $plate = $this->figure('healthy-plate', 'A chickpea, avocado and vegetable salad from the demonstration.');
        $partners = $this->figure('mds-lancet-partners', 'Representatives of MDS-Lancet Laboratories, a long-standing partner of the Foundation.');
        $audience = $this->figure('full-house-audience', 'A full house of patients, professionals, partners, media and members of the public.');
        $guests = $this->figure('vip-guests', 'Guests and organisers on the red carpet at the University of Ghana Medical Centre.');
        $management = $this->figure('thyroid-management-presentation', 'The presentation on treatment options for thyroid disorders.');
        $founder = $this->figure('founder-closing-call', 'Dr. Nana Adwoa Konadu Dsane, Founder of Thyroid Ghana Foundation, called on participants to become ambassadors for thyroid awareness.');
        $volunteers = $this->figure('volunteers-team', 'The volunteers and organising team behind the programme.');
        $group = $this->figure('participants-group-photo', 'Participants, speakers and partners at the close of the forum.');

        return <<<HTML
<p>As part of activities marking International Thyroid Awareness Week 2026, Thyroid Ghana Foundation, in collaboration with the University of Ghana Medical Centre (UGMC) and key stakeholders, organised an educational forum on <strong>26 May 2026</strong> under the theme <strong>“Thyroid and Nutrition.”</strong></p>

<p>The event brought together healthcare professionals, nutrition experts, patients living with thyroid disorders, partner organisations, media representatives and members of the public to increase awareness of thyroid diseases and highlight the important relationship between nutrition and thyroid health.</p>

<p>The programme set out to improve public understanding of thyroid disorders, promote healthy nutritional practices, encourage early detection and screening, and provide practical information for people living with thyroid conditions.</p>

{$audience}

<h2>Opening session</h2>

<p>The programme opened with a prayer led by Rev. Joseph Agyaaku Ofosu, Chaplain of UGMC. Participants were then welcomed by Prof. P. F. Ayeh-Kumi, Board Chair of the Thyroid Ghana Foundation, who emphasised the Foundation’s commitment to raising awareness, supporting people living with thyroid disorders, and advocating for timely diagnosis and access to quality care.</p>

<p>Dr. Nana Adwoa Konadu Dsane, Founder of Thyroid Ghana Foundation, outlined the purpose of the gathering and highlighted the growing burden of thyroid diseases in Ghana. She stressed the need for increased public education and the critical role nutrition plays in the prevention and management of thyroid disorders.</p>

<p>A solidarity message was delivered on behalf of the management of the University of Ghana Medical Centre, reaffirming the institution’s commitment to raising thyroid awareness and improving patient outcomes through multidisciplinary care.</p>

{$opening}

{$guests}

<h2>Expert presentations</h2>

<h3>The role of dietitians in preventing and managing thyroid disorders</h3>

<p>The first presentation was delivered by Rev. Prince Baidoo, President of the Ghana Academy of Nutrition and Dietetics (GAND). He explained that thyroid disorders are among the most common endocrine conditions worldwide, affecting approximately 5–10% of the global population. In Africa, thyroid diseases remain a significant public health concern because of factors such as inadequate iodine intake and a high number of undiagnosed cases. In Ghana, the prevalence of thyroid disorders is estimated at approximately 2–4%.</p>

<p>The presentation highlighted the unique role of dietitians in:</p>
<ul>
<li>Promoting healthy dietary practices and adequate iodine intake.</li>
<li>Preventing nutritional deficiencies associated with thyroid disorders.</li>
<li>Providing individualised nutrition counselling.</li>
<li>Supporting healthy weight management.</li>
<li>Educating patients on lifestyle modifications that improve treatment outcomes.</li>
<li>Facilitating long-term behavioural change through evidence-based nutrition interventions.</li>
</ul>

<h3>Thyroid and nutrition: linking food to hormone health</h3>

<p>Emelia Dery Gbogr, Head of Dietetics at UGMC, explored the relationship between food, nutrient intake and thyroid hormone production. Key areas discussed included:</p>
<ul>
<li>The importance of iodine in thyroid hormone synthesis.</li>
<li>The role of protein and tyrosine in maintaining thyroid function.</li>
<li>Appropriate consumption of goitrogen-containing foods.</li>
<li>The contribution of gut health to immune function and hormonal balance.</li>
<li>Practical dietary recommendations for people living with thyroid disorders.</li>
</ul>

<p>Participants were encouraged to adopt balanced diets rich in essential nutrients while seeking professional guidance before making significant dietary changes.</p>

{$nutrition}

<h3>Management of thyroid disorders</h3>

<p>Dr. Michael Damah, Head of the Ear, Nose and Throat (ENT) Department at UGMC, presented the treatment options available for thyroid disorders and the common conditions encountered in clinical practice, including:</p>
<ul>
<li>Medical treatment and monitoring.</li>
<li>Surgical interventions where indicated.</li>
<li>Lifestyle and dietary modifications.</li>
<li>Long-term follow-up and multidisciplinary care.</li>
</ul>

<p>Dr. Damah emphasised the importance of early diagnosis and adherence to treatment plans to improve quality of life and reduce complications associated with thyroid disorders.</p>

{$management}

<h2>Patient testimonies</h2>

<p>One of the most impactful segments of the programme was the patient testimony session. Individuals living with thyroid disorders shared their personal experiences, including challenges related to delayed diagnosis, the emotional and psychological effects of the condition, their treatment journeys and recovery.</p>

<p>Their stories highlighted the real-life impact of thyroid diseases on individuals and families, and demonstrated the importance of awareness, support systems and access to quality healthcare. The testimonies generated meaningful discussion and inspired participants to prioritise their thyroid health and seek medical attention when symptoms arise.</p>

{$testimonies}

<h2>Expert panel discussion</h2>

<p>An interactive panel discussion followed, featuring Dr. Michael Damah, Emelia Dery Gbogr, RD Elorm and other health professionals. Participants asked questions and received practical guidance on:</p>
<ul>
<li>Nutrition and thyroid health.</li>
<li>Weight management.</li>
<li>Medication adherence.</li>
<li>Lifestyle modifications.</li>
<li>Screening and early detection.</li>
<li>Myths and misconceptions surrounding thyroid disorders.</li>
</ul>

<p>The discussion enhanced participants’ understanding of thyroid disease management and reinforced the value of multidisciplinary care.</p>

{$panel}

<h2>Know Your Thyroid Health: screening, symptoms and nutrition</h2>

<p>A dedicated education segment, led by representatives of the UGMC Dietetics Department and RD Sarah Salomey Kailey Okine, focused on the common signs and symptoms of thyroid disorders, the importance of routine screening and early detection, practical nutrition strategies for maintaining thyroid health, and healthy food choices that support thyroid function.</p>

<p>Participants also benefited from practical demonstrations and educational materials designed to encourage healthy lifestyle practices.</p>

{$demonstration}

{$plate}

<h2>Partner support and goodwill messages</h2>

<p>A goodwill message was delivered by representatives of MDS-Lancet Laboratories, a long-standing partner of Thyroid Ghana Foundation. The message reaffirmed the importance of partnerships in advancing thyroid awareness, promoting screening services, and improving access to diagnostic and laboratory support for patients.</p>

{$partners}

<h2>A call to action</h2>

<p>In her closing presentation, Dr. Nana Adwoa Konadu Dsane summarised the key lessons from the programme and called on participants to become ambassadors for thyroid awareness within their communities. She encouraged the public to:</p>
<ul>
<li>Seek medical attention when symptoms arise.</li>
<li>Prioritise routine health screening.</li>
<li>Adopt healthy dietary habits.</li>
<li>Support individuals living with thyroid disorders.</li>
<li>Participate in awareness and advocacy initiatives.</li>
</ul>

<p>She further highlighted the need for sustained public education, research and policy support to improve thyroid health outcomes in Ghana.</p>

{$founder}

<h2>Conclusion</h2>

<p>International Thyroid Awareness Week 2026 successfully increased awareness of thyroid disorders and strengthened public understanding of the important role nutrition plays in thyroid health. Through expert presentations, patient testimonies, panel discussions and educational demonstrations, participants gained practical knowledge that can contribute to the prevention, early detection and effective management of thyroid conditions.</p>

<p>The event reinforced Thyroid Ghana Foundation’s commitment to improving awareness, supporting patients, promoting early diagnosis, and advocating for better thyroid healthcare services in Ghana.</p>

<p>The Foundation expresses its sincere appreciation to the University of Ghana Medical Centre, the Ghana Academy of Nutrition and Dietetics, MDS-Lancet Laboratories, healthcare professionals, volunteers, partners, media representatives and all the participants whose contributions made the programme a success.</p>

{$volunteers}

{$group}
HTML;
    }
}
