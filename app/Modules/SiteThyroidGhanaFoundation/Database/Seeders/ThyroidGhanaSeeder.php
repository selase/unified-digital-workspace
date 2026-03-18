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
        foreach (['Awareness', 'Patient Support', 'Research', 'Events', 'Partnerships'] as $name) {
            Category::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => \Illuminate\Support\Str::slug($name)],
                ['name' => $name, 'is_active' => true, 'sort_order' => 0]
            );
        }
    }

    private function seedTags(Tenant $tenant): void
    {
        foreach (['thyroid', 'health', 'ghana', 'awareness-week', 'surgery', 'fundraising', 'west-africa'] as $tag) {
            Tag::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $tag],
                ['name' => ucwords(str_replace('-', ' ', $tag))]
            );
        }
    }

    private function seedPages(Tenant $tenant): void
    {
        $pageType = PostType::where('tenant_id', $tenant->id)->where('slug', 'page')->first();

        $pages = $this->getPageContent();

        foreach ($pages as $pageData) {
            $post = Post::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'post_type_id' => $pageType->id, 'slug' => $pageData['slug']],
                array_merge($pageData, ['post_type_id' => $pageType->id, 'author_id' => $this->authorId])
            );

            // Set page template for media-gallery page
            if ($pageData['slug'] === 'media-gallery') {
                \App\Modules\CmsCore\Models\PostMeta::query()->updateOrCreate(
                    ['post_id' => $post->id, 'key' => 'page_template'],
                    ['value' => 'media-gallery', 'tenant_id' => $tenant->id]
                );
            }
        }
    }

    private function seedNews(Tenant $tenant): void
    {
        $newsType = PostType::where('tenant_id', $tenant->id)->where('slug', 'post')->first();
        $awarenessCategory = Category::where('tenant_id', $tenant->id)->where('slug', 'awareness')->first();

        $articles = $this->getNewsContent();

        foreach ($articles as $article) {
            $post = Post::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'post_type_id' => $newsType->id, 'slug' => $article['slug']],
                array_merge($article, ['post_type_id' => $newsType->id, 'author_id' => $this->authorId])
            );

            if ($awarenessCategory) {
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

        // Clear and rebuild items
        $menu->items()->delete();

        $pageType = PostType::where('tenant_id', $tenant->id)->where('slug', 'page')->first();

        $items = [
            ['label' => 'Home', 'slug' => 'home', 'sort_order' => 0],
            ['label' => 'About', 'slug' => 'about', 'sort_order' => 1],
            ['label' => 'The Challenge', 'slug' => 'the-challenge', 'sort_order' => 2],
            ['label' => 'Thyroid Disease', 'slug' => 'thyroid-disease', 'sort_order' => 3],
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
            'footer_text' => '© '.date('Y').' Thyroid Ghana Foundation. All rights reserved. Member of Thyroid Federation International.',
            'active_theme' => 'default',
        ];

        foreach ($settings as $key => $value) {
            Setting::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'group' => 'theme', 'key' => $key],
                ['value' => $value]
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getPageContent(): array
    {
        return [
            [
                'slug' => 'home',
                'title' => 'Home',
                'excerpt' => 'Creating awareness of thyroid diseases in Ghana',
                'body' => '<p>Welcome to the Thyroid Ghana Foundation.</p>',
                'status' => 'published',
                'published_at' => now(),
            ],
            [
                'slug' => 'about',
                'title' => 'About Us',
                'excerpt' => 'Learn about our mission, vision, and the work we do across Ghana.',
                'body' => '<h2>Who We Are</h2><p>The Thyroid Ghana Foundation is a Non-Governmental Organization dedicated to creating awareness of thyroid disorders in Ghana and bringing prompt and appropriate treatment to those affected by thyroid diseases.</p><h2>Our Mission</h2><p>Creating awareness of thyroid diseases in Ghana, creating opportunities for early detection of thyroid problems, supporting thyroid research and institutions involved in thyroid disease management, providing access to affordable treatment and advocating for improved healthcare practices for thyroid disease patients in the country.</p><h2>Our Vision</h2><p>A Ghana where thyroid diseases are detected early, treated effectively, and no patient is left behind due to lack of awareness or access to healthcare.</p><h2>Our Objectives</h2><h3>Create Public Awareness</h3><p>Thyroid disorder remains the second most common endocrinological disorder in adults, yet remains relatively unknown to the public and some healthcare practitioners. This lack of awareness makes accurate diagnosis difficult since thyroid disease symptoms resemble other common ailments. Improved public awareness can lead to early detection and help individuals alert physicians to suspected thyroid conditions.</p><h3>Support Thyroid Research</h3><p>Research is necessary to equip healthcare practitioners and policy makers with accurate information for patient care decisions. The foundation solicits funds and logistics to support research projects and establish data collection systems at various healthcare centers.</p><h3>Support Affected Persons</h3><p>The foundation is developing a patient registry starting from Greater Accra, establishing a system providing free thyroid medication for those unable to afford treatment, and ultimately aims to build a thyroid care unit within Korle-Bu Teaching Hospital.</p><h2>Our History</h2><p>Founded in July 2018 by Mrs. Nana Adwoa Konadu Dsane following her own battle with hyperthyroidism, the Thyroid Ghana Foundation has grown from a small awareness initiative into a nationally recognized organization. As a proud member of the Thyroid Federation International, we connect Ghanaian patients and healthcare providers with global best practices in thyroid disease management.</p>',
                'status' => 'published',
                'published_at' => now(),
            ],
            [
                'slug' => 'the-founder',
                'title' => 'The Founder',
                'excerpt' => 'Mrs. Nana Adwoa Konadu Dsane — Founder & President of the Thyroid Ghana Foundation.',
                'body' => '<h2>Mrs. Nana Adwoa Konadu Dsane</h2><p><strong>Founder & President, Thyroid Ghana Foundation</strong></p><p>Mrs. Dsane holds a Commonwealth Executive Master of Business Administration (CEMBA) from Kwame Nkrumah University of Science and Technology and a BSc in Business Management Studies from the University of Cape Coast. She is pursuing a PhD in Human Resources at the University of South Africa, researching "Leadership and Empowerment on Organizational Citizenship Behaviour in Public Universities in Ghana."</p><p>She maintains numerous professional certifications including Fellow of the Chartered Institute of Leadership and Governance, Chartered Human Resource Management Practitioner, Chartered Professional Administrator, and Chartered Management Consultant.</p><h2>Career Background</h2><p>Mrs. Dsane currently serves as Deputy Director of the Medical and Scientific Research Centre at the University of Ghana Medical Centre. She brings approximately 20 years of administrative experience across research, health, education, and corporate sectors.</p><p>Previous roles include positions within the Office of Research Innovation and Development, Office of the Provost at the College of Health Sciences, the University of Ghana Medical School\'s Haematology Department, The Hunger Project-Ghana, and Trassaco Real Estate Company.</p><h2>The Personal Story Behind the Foundation</h2><p>Mrs. Dsane established the Thyroid Ghana Foundation following her own battle with hyperthyroidism. Experiencing the challenges of diagnosis and treatment firsthand motivated her to create an organization that would ensure other patients had better access to information, support, and affordable care.</p><h2>Additional Leadership Roles</h2><p>She serves as Board Chairperson of the Global Transformational Agents Foundation and Vice-Chairperson of the National Executive Council of the Chartered Institute of Leadership and Governance (Ghana Chapter).</p><h2>Awards & Recognition</h2><ul><li>CILG Personality of the Year 2018</li><li>African Regional Award for 100 Most Inspiring Individuals in Africa (2021)</li><li>UPF Merit Ambassador for Peace Award (2021)</li><li>Outstanding Philanthropic Award — Ladies in Business Magazine Global (2022)</li></ul>',
                'status' => 'published',
                'published_at' => now(),
            ],
            [
                'slug' => 'our-team',
                'title' => 'Our Team',
                'excerpt' => 'Meet the dedicated leadership team behind the Thyroid Ghana Foundation.',
                'body' => '<h2>Management Team</h2><div><h3>Nana Adwoa Konadu Dsane (Mrs.)</h3><p><strong>Founder & President</strong></p><p>Established the foundation in 2018 following her personal battle with hyperthyroidism. A seasoned administrator with over 20 years of experience.</p></div><div><h3>Rev. Prof. Patrick F. Ayeh-Kumi</h3><p><strong>Management Board Chair</strong></p><p>A distinguished academic and researcher who brings decades of medical expertise to the foundation\'s governance and strategic direction.</p></div><div><h3>Mr. Leslie Chartey Kumahlor</h3><p><strong>Head of Operations</strong></p><p>Oversees the day-to-day operations and ensures our programs reach communities across Ghana effectively.</p></div><div><h3>Dr. Joyce Emefa Addo-Klah</h3><p><strong>Public Relations Officer</strong></p><p>Leads our communications strategy and public engagement initiatives.</p></div><div><h3>Frank Anyimadu</h3><p><strong>Consultant Dietician</strong></p><p>Provides nutritional guidance and develops dietary programs for thyroid patients.</p></div><div><h3>Justice Kwesi Baah</h3><p><strong>Research Coordinator</strong></p><p>Coordinates research initiatives and partnerships with academic institutions.</p></div><h2>Advisory Board</h2><div><h3>Dr. Josephine Akpalu</h3><p><strong>Advisory Board Chair</strong></p></div><div><h3>Dr. Alfred Tetteh</h3><p><strong>Member, Management Board</strong></p></div><div><h3>Dr. Matilda Asante</h3><p><strong>Member, Management Board</strong></p></div>',
                'status' => 'published',
                'published_at' => now(),
            ],
            [
                'slug' => 'our-plan',
                'title' => 'Our Plan',
                'excerpt' => 'A four-phase action plan to transform thyroid healthcare in Ghana.',
                'body' => '<h2>Four-Phase Action Plan</h2><p>The Foundation has structured a comprehensive initiative beginning in Accra with four distinct phases designed to systematically address the thyroid health crisis in Ghana.</p><h3>Phase I: Awareness Creation</h3><p>Educate medical practitioners especially those stationed in rural areas about thyroid disease and its symptoms. Utilize flyers, billboards, media, and other communication channels for public awareness. Liaise with professional bodies for screening programs in underserved communities. Advocate for thyroid testing in standard hospital protocols. Create blogs and online journals for spreading accurate information. Engage government agencies to establish a dedicated Thyroid awareness month.</p><h3>Phase II: Affordable Treatment Access</h3><p>Reduce financial barriers to thyroid care for low-income patients through free distribution of medication to needy patients via partnerships with relevant agencies. Develop a programme encouraging thyroid patients to donate unused medications. Advocate for the inclusion of radioactive iodine treatment under the National Health Insurance Scheme.</p><h3>Phase III: Research Data Infrastructure</h3><p>Establish data collection points on thyroid disease at various health centers. Support thyroid research by providing logistics for health records. Make data accessible to local and international research groups, policy makers and health bodies.</p><h3>Phase IV: Dedicated Care Center</h3><p>Construct a specialized thyroid treatment facility providing integrated services from diagnosis to surgery where necessary at a subsidized cost. Facilitate professional knowledge-sharing on best practices in thyroid care.</p>',
                'status' => 'published',
                'published_at' => now(),
            ],
            [
                'slug' => 'the-challenge',
                'title' => 'The Challenge',
                'excerpt' => 'Understanding the scale of thyroid disease in Ghana and the challenges we face.',
                'body' => '<h2>The Thyroid Health Challenge in Ghana</h2><p>A comprehensive study examining thyroid cases at Korle-Bu Teaching Hospital (KBTH) spanning 2004-2010 revealed alarming findings about thyroid disease in Ghana.</p><h2>Key Statistics</h2><ul><li><strong>1,300 cases</strong> reported during the study period</li><li><strong>185.7 cases</strong> annually on average</li><li>Age range: <strong>1 to 86 years</strong>, with peak incidence in the 30-39 age group</li><li><strong>87.8% were female patients</strong> (1,141 of 1,300 cases)</li></ul><h2>A Wide Spectrum of Disease</h2><p>A wide spectrum of thyroid disorders exists in Ghana. Recent research indicates that salt iodization has reduced certain disease types while others have increased. Prevalence varies by geographic location, environmental factors, dietary iodine levels, and population characteristics. Women predominantly present with palpable anterior neck swelling.</p><h2>Diagnostic Challenges</h2><p>Ghana faces significant obstacles in thyroid disease detection. Robust diagnostic facilities for thyroid disorders are generally lacking in most countries in Africa. Thyroid disease shares symptoms with other conditions, complicating identification. Insufficient physician awareness means patients may receive incorrect diagnoses. Patients often undergo multiple tests before appropriate thyroid testing, increasing costs and delays.</p><h2>Treatment in Ghana</h2><p>Primary treatment options include pharmacotherapy, surgery (the more affordable option in Ghana), and radioactive iodine therapy. However, access to all three varies significantly across the country.</p>',
                'status' => 'published',
                'published_at' => now(),
            ],
            [
                'slug' => 'thyroid-disease',
                'title' => 'Understanding Thyroid Disease',
                'excerpt' => 'Learn about the thyroid gland, types of thyroid disease, symptoms, and treatments.',
                'body' => '<h2>What is the Thyroid?</h2><p>The thyroid is a small, butterfly-shaped gland located at the base of the neck just below the Adam\'s apple. It produces hormones that control how the body uses energy (metabolism) and protein synthesis. These hormones affect nearly every organ in your body.</p><h2>What is Thyroid Disease?</h2><p>Thyroid diseases involve benign or malignant disorders affecting thyroid structure and function. They occur when the gland produces too much hormone (hyperthyroidism) or too little (hypothyroidism).</p><h2>Hyperthyroidism (Overactive Thyroid)</h2><p>When the thyroid produces too much hormone, your body\'s processes speed up.</p><h3>Symptoms</h3><ul><li>Restlessness and nervousness</li><li>Severe headaches and neck pain</li><li>Rapid heartbeat and irritability</li><li>Increased sweating and tremors</li><li>Anxiety and sleep difficulties</li><li>Thin skin, brittle hair and nails</li><li>Muscle weakness and weight loss</li><li>Bulging eyes (in Graves\' disease)</li><li>Difficulty concentrating</li></ul><h3>Common Causes</h3><p>Graves\' disease (autoimmune condition), thyroid nodules, excessive TSH secretion, hypothyroidism medication overdose, and thyroiditis (inflammation of the thyroid).</p><h2>Hypothyroidism (Underactive Thyroid)</h2><p>When the thyroid doesn\'t produce enough hormone, body processes slow down.</p><h3>Symptoms</h3><ul><li>Fatigue and weakness</li><li>Dry skin and cold sensitivity</li><li>Memory problems and depression</li><li>Weight gain and slow heart rate</li><li>Constipation</li><li>In severe cases, coma</li></ul><h3>Common Causes</h3><p>Hashimoto\'s disease (autoimmune), thyroiditis, congenital hypothyroidism, post-surgical removal, radiation treatment, certain medications, pituitary dysfunction, and iodine imbalance.</p><h2>Diagnosis</h2><p>Blood tests measuring thyroid hormones (FT3, FT4) and TSH (thyroid-stimulating hormone) levels are the primary diagnostic tools. Imaging tests and biopsies may also be used.</p><h2>Treatment Options</h2><ul><li><strong>Hyperthyroidism:</strong> Radioactive iodine treatment, anti-thyroid medications, or surgery</li><li><strong>Hypothyroidism:</strong> Lifelong synthetic thyroid hormone replacement (typically levothyroxine)</li><li><strong>Thyroid Cancer:</strong> Surgical removal (thyroidectomy), potentially including affected lymph nodes</li></ul><h2>Prevention</h2><p>Protect your thyroid during X-rays (request a thyroid collar). Monitor family history of thyroid disease. Maintain adequate iodine intake through a balanced diet.</p>',
                'status' => 'published',
                'published_at' => now(),
            ],
            [
                'slug' => 'do-i-have-thyroid-disease',
                'title' => 'Do I Have Thyroid Disease?',
                'excerpt' => 'Risk factors and self-assessment guide for thyroid conditions.',
                'body' => '<h2>Could You Have a Thyroid Condition?</h2><p>The following factors may indicate an increased likelihood of thyroid disease. This is not a diagnosis — please consult a healthcare provider for proper evaluation.</p><h2>Demographic & Family Factors</h2><ul><li>Women have a higher chance of developing thyroid disease than men</li><li>Family history of autoimmune disorders</li><li>Existing autoimmune conditions</li></ul><h2>Lifestyle & Environmental Factors</h2><ul><li>Excessive consumption of soy products</li><li>Taking iodine or kelp supplements</li><li>Using thyroid hormones without a diagnosed disease</li><li>Previous radiation exposure or repeated neck X-rays</li><li>High stress levels</li><li>History of smoking</li></ul><h2>Medical Conditions to Watch</h2><ul><li>Treatment-resistant depression</li><li>Chronic Fatigue Syndrome or Fibromyalgia</li><li>Burnout or persistent exhaustion</li><li>Carpal tunnel syndrome</li><li>Reproductive issues (multiple miscarriages, infertility)</li><li>Blood pressure abnormalities</li><li>Elevated cholesterol unresponsive to diet or medication</li></ul><h2>Physical Symptoms to Monitor</h2><ul><li>Unexplained weight changes (gain or loss)</li><li>Post-pregnancy fatigue</li><li>Vision problems (double vision, light sensitivity)</li><li>Menstrual irregularities or early menopause</li><li>Anemia or vitamin deficiencies (B12, D)</li><li>Muscle weakness and joint pain</li><li>Leg swelling or edema</li><li>Voice changes or hoarseness</li><li>Seasonal mood sensitivity</li></ul><p><strong>Important:</strong> These are non-exhaustive indicators. If you identify with several of these factors, we recommend consulting with a healthcare provider for a thyroid function test.</p><h2>Next Steps</h2><p>Ask your doctor for a simple blood test measuring TSH and thyroid hormone levels. Early detection leads to better outcomes. Contact us at +233 (024) 337 6304 for guidance on where to get tested.</p>',
                'status' => 'published',
                'published_at' => now(),
            ],
            [
                'slug' => 'faq',
                'title' => 'Frequently Asked Questions',
                'excerpt' => 'Common questions about thyroid disease, testing, and treatment.',
                'body' => '<h2>Frequently Asked Questions</h2><h3>Do I need to fast before a thyroid blood test?</h3><p>Medical opinions vary on fasting before blood tests. Some doctors recommend fasting while others do not. If fasting is not observed, the TSH value may be slightly lower and the FT4 value slightly higher. It is recommended to always have blood drawn at a consistent time of day for reliable comparisons.</p><h3>How long after a medication change should I get tested?</h3><p>After 6 weeks, the values in your blood are stable. So only after at least 4, but preferably after 6 weeks should you have blood drawn again. Some patients wait 8 weeks. The medication\'s effects may take longer to appear in blood work than expected.</p><h3>What blood values indicate proper treatment?</h3><p>The ideal values vary by individual. A TSH value under 2 is almost always desirable, but some people feel best at a higher TSH. With T4 treatment alone, FT4 should be in the upper half of the normal range or slightly above. Normal reference values: TSH 0.4-4.0 mU/l; FT4 9-24 pmol/l.</p><h3>What is an autoimmune disease?</h3><p>Not all thyroid disorders are autoimmune — thyroid cancer, for example, is not. Common autoimmune conditions that may co-occur with thyroid disease include pernicious anemia, vitiligo, diabetes mellitus, Addison\'s disease, and rheumatoid arthritis. Autoimmune disorders can be detected by measuring specific antibodies in the blood.</p><h3>Are all thyroid diseases autoimmune?</h3><p>No. Thyroid cancer is not autoimmune, and pituitary conditions can affect thyroid hormone levels independently. While Hashimoto\'s thyroiditis and Graves\' disease are autoimmune, many other thyroid conditions have different causes.</p><h3>How can I support the Thyroid Ghana Foundation?</h3><p>You can donate, volunteer, become a member, or partner with us. Contact us at info@thyroidghanafoundation.org or call +233 (024) 337 6304 for more information.</p>',
                'status' => 'published',
                'published_at' => now(),
            ],
            [
                'slug' => 'donate',
                'title' => 'Support Our Cause',
                'excerpt' => 'Your donation helps us reach more patients and fund critical thyroid research.',
                'body' => '<h2>Make a Difference Today</h2><p>Your generous contribution directly supports our mission to improve thyroid health outcomes across Ghana. Every donation helps us:</p><ul><li>Fund screening programs in underserved communities</li><li>Provide medication subsidies for patients who cannot afford treatment</li><li>Support surgical interventions for thyroid cancer patients</li><li>Run awareness campaigns during World Thyroid Awareness Week</li><li>Train healthcare workers in thyroid disease detection</li></ul><h2>How to Donate</h2><p>We accept donations through mobile money, bank transfer, and online payment. Contact us at <strong>+233 (024) 337 6304</strong> or email <strong>info@thyroidghanafoundation.org</strong> for donation details.</p><h2>Corporate Partnerships</h2><p>We welcome corporate partnerships and sponsorships. Partner with us to make a lasting impact on thyroid health in Ghana and fulfil your corporate social responsibility goals.</p>',
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
            [
                'slug' => 'media-gallery',
                'title' => 'Media Gallery',
                'excerpt' => 'Watch videos, view photos, and explore media content from the Thyroid Ghana Foundation.',
                'body' => '<h2>Videos & Media</h2><p>Explore our collection of educational videos, awareness campaign highlights, patient stories, and event coverage. Follow us on YouTube and social media for the latest updates.</p><h2>Awareness Campaigns</h2><p>Each year during World Thyroid Awareness Week, we produce educational content to help Ghanaians understand thyroid disease, its symptoms, and the importance of early detection.</p><h2>Patient Stories</h2><p>Hear from patients who have benefited from our support programs. Their stories inspire us to continue our work and reach even more communities across Ghana.</p><h2>Stay Connected</h2><p>Follow us on social media for the latest news, events, and educational content about thyroid health in Ghana.</p>',
                'status' => 'published',
                'published_at' => now(),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getNewsContent(): array
    {
        return [
            [
                'slug' => 'ghana-health-awards-honors-launched',
                'title' => 'Ghana Health Awards and Honors Launched',
                'excerpt' => 'Akoma Productions on Wednesday, 30th November, 2022 launched the maiden edition of the Ghana Health Awards and Honors.',
                'body' => '<p>Akoma Productions on Wednesday, 30th November, 2022 launched the maiden edition of the Ghana Health Awards and Honors.</p><p>The graceful event which took place at the premises of the Medical Training and Simulation Centre, Legon in Accra saw in attendance invited guests, media and dignitaries including; Rev. Prof. Patrick F. Ayeh-Kumi, Dr Abena Engmann (Board Chairperson of Ghana Health Awards &amp; Honors), Dr Solomon Brookman (Head, General Surgery Dept, UGMC), and Dr Darius Osei (Chief Executive Officer of the University of Ghana Medical Centre Limited) who gave an opening remark to commence the event.</p><p>The Ghana Health Awards and Honors is dedicated to the Ghana Health industry recognizing individuals and organizations making positive impact in the health sector in Ghana.</p><p>Speaking at the press soirée, Mrs Nana Adwoa Konadu Dsane, the founder of the Ghana Thyroid Foundation and the Ghana Health Awards and Honors reiterated that, health workers mostly boil the ocean to save lives hence the need to acknowledge their sacrifices.</p><blockquote><p>"Though people have had bad experiences when it comes to their healthcare providers, there are some who are doing so well and would even not go home when there\'s a life to save. I\'ve come across a lot of them because of the Thyroid Ghana Foundation. Especially during the COVID-19 time, some stayed away from their families for 2 weeks without their families seeing them. Don\'t you think they deserve some recognition? I think this is long overdue!"</p></blockquote><p>The Ghana Health Awards and Honors themed "Our Health, Our Heroes" is scheduled to take place in March 2023 at the Medical Training and Simulation Centre, Legon in Accra.</p><h3>Award Categories</h3><p><strong>Outstanding Awards:</strong> Outstanding Best Health Worker, Best CEO, Best Hospital Administrator, Best Human Resource Director, Best Internal Audit Director, Best Finance Director.</p><p><strong>Healthcare Professionals:</strong> Best Surgeon, Best Emergency Medicine Consultant, Best Endocrinologist, Best Pediatrician, Best Obstetrics/Gynaecologist, Best Psychologist, Best Urologist, Best Neurologist, Best Neurosurgeon, Best Dentist, Best Rheumatologist, Best Radiologist, Best Ophthalmologist, Best Dietician, Best Occupational Therapist, Best Physician Assistant, Best Radiotherapist, Best General Nurse, Best Theatre Nurse, Best Midwife, Best Nurse in Research, Best Public Health Nurse, Best Health Innovationist, Best Physiotherapist.</p><p><strong>Health Facilities:</strong> Best Pharmacy, Best Pharmaceutical Company, Best Laboratory Facility, Best Emerging Laboratory, Best Private Health Facility, Best Public Health Facility, Best Dental Facility, Best Eye Facility, Best Fitness Center, Best Physiotherapy Centre, Best Healthcare Insurance Provider, Best Company with CSR practices in Health, Best Herbal Facility.</p>',
                'status' => 'published',
                'published_at' => '2022-12-23',
            ],
            [
                'slug' => 'founder-featured-humble-beginnings',
                'title' => 'Nana Adwoa Konadu Dsane — Feature on Humble Beginning Stories',
                'excerpt' => 'An extended profile detailing the founder\'s personal health journey from misdiagnosis to establishing the Thyroid Ghana Foundation.',
                'body' => '<p>By mid-2017, Mrs. Dsane had returned to Ghana from the United States after birthing her third baby. She had a pre-labour Spontaneous Rupture of the Membranes (SRM) prior to delivery. When she started experiencing severe abdominal pains several weeks after returning to Ghana, her natural conclusion was that the discomfort was an after-effect of the SRM.</p><p>Some weeks prior to the abdominal pains, she had also experienced a recurrent sore throat which persisted even after taking medications prescribed from the staff clinic. The sore throat would heal after each dose of treatment and come back a few days later.</p><p>Her Gynaecologist requested an ultrasound to check the abdominal pain, but results showed no infection. Pain relievers were prescribed but the pain returned worse after three full weeks.</p><blockquote><p>"I have reasons to believe that I was living with this thyroid disorder for many years before being diagnosed."</p></blockquote><p>In 2002, after conceiving her first son, she was told she had become anaemic. She had also been battling with a severe case of ulcer and food allergies since secondary school. Interestingly, after being diagnosed with hyperthyroidism and put on treatment, her Haemoglobin level never dropped to anaemic levels again, nor did she have any ulcer episodes — suggesting her thyroid problems may have started much earlier.</p><p>She established the Thyroid Ghana Foundation following the battle with hyperthyroidism which took almost 8 months before she was properly diagnosed. She was working in one of the biggest hospitals in Ghana for over 16 years and had not heard anything called hyperthyroidism, let alone Graves\' disease. Within those months, she had lost almost 25kg, and her joints became weak.</p><h3>Overcoming Challenges</h3><p>The biggest initial hurdles were getting affected persons to accept their condition, getting funds to support needy patients, and getting institutions involved in thyroid care to provide discounts. The foundation put in place programs including a WhatsApp support platform where affected persons can post thyroid-related issues for help from medical practitioners, and the thyroid patients forum.</p><p>In terms of institutional support, proposals were sent to organizations and affected persons now get discounts of 10-30% on investigations from 3 of the biggest laboratory and imaging organizations in Ghana. The foundation has collaborated with the University of Ghana Medical Centre since May 2021 to provide thyroid surgeries at a subsidized fee of one-third of the full cost.</p><p>For those unable to afford even the subsidized fee, the foundation visits their places of religious worship to raise funds. Pharmaceutical companies also donate drugs to be shared with affected persons.</p><blockquote><p>"If you have good health, you have wealth. Let\'s not attribute every health challenge to some \'witches\' and be ignorant about seeking medical interventions. Yes, it\'s great to pray but let\'s back our prayers with the needed action, for the bible says, \'faith without works, is dead\' — James 4:17."</p></blockquote>',
                'status' => 'published',
                'published_at' => '2022-07-13',
            ],
            [
                'slug' => 'world-thyroid-awareness-week-launch',
                'title' => 'Launch of the 14th World Thyroid Awareness Week',
                'excerpt' => 'The foundation launched World Thyroid Awareness Week with the theme "It\'s Not You, It\'s Your Thyroid" in collaboration with UGMC.',
                'body' => '<p>The Thyroid Ghana Foundation launched the 14th World Thyroid Awareness Week celebration under the theme <strong>"Thyroid and Communication — It\'s Not You, It\'s Your Thyroid"</strong> in collaboration with the University of Ghana Medical Centre Ltd (UGMC).</p><p>A significant component of this launch involved the second phase of a subsidized thyroid surgery project. This initiative aims to provide financial assistance to thyroid patients who require surgical intervention at UGMC.</p><p>The event brought together healthcare professionals, patients, and community leaders for a week of education and screening. Activities included free thyroid screenings at community health centers and educational workshops for healthcare workers across Accra.</p>',
                'status' => 'published',
                'published_at' => '2022-06-15',
            ],
            [
                'slug' => 'thyroid-disease-and-coronavirus',
                'title' => 'Thyroid Disease and Coronavirus',
                'excerpt' => 'Comprehensive pandemic guidance for thyroid patients addressing concerns about COVID-19 risk, medications, and safety.',
                'body' => '<p>It is very understandable that during this period of global fear and panic most thyroid patients would be extra worried about their health with regards to their chances of contracting the coronavirus, managing both an infection and their thyroid disease, and the risk involved in attending hospitals and taking thyroid medications.</p><h3>Are individuals with autoimmune thyroid disease at risk of COVID-19 infection?</h3><p>COVID-19 is a new virus, so there is currently no information on how it affects individuals with thyroid disease. However thyroid disease is not known to be associated with increased risk of viral infections in general, nor is there an association between thyroid disease and severity of the viral infection. An autoimmune thyroid disease does not make one immunocompromised. The part of the immune system responsible for autoimmune thyroid conditions is separate to the immune system responsible for fighting off viral infections such as COVID-19.</p><h3>Does medication for my thyroid disorder suppress my immune system?</h3><p>Neither levothyroxine, nor carbimazole nor propylthiouracil, are immunomodulatory therapies — they do not change nor weaken your immune system. However, some patients with thyroid eye disease will be on high doses of steroid medication which can suppress the immune system.</p><h3>Are patients who have had radioiodine therapy or thyroid surgery at higher risk?</h3><p>There is no evidence to show that radioiodine therapy or thyroid surgery for benign thyroid disease would put a patient at higher risk of COVID-19 infection. However, rules for staying safe must be strictly adhered to both prior and post thyroidectomy. It would be very difficult for a patient to manage a COVID-19 infection while recovering from thyroid surgery. The Surgical department of the Korle-Bu Teaching Hospital will carry out scheduled thyroid surgeries till otherwise instructed by the Ministry of Health.</p><h3>Is it safe to visit the endocrine clinic during this pandemic?</h3><p>The Endocrine clinic at the Korle-Bu Teaching Hospital has put in the necessary infection control measures to ensure staff and patient safety. The Clinic still runs on Tuesday mornings with the added option for telephone consultations for patients who are unable to visit the premises.</p><h3>Safety Guidelines for Thyroid Patients</h3><ul><li>Ensure they take their medication and manage their condition properly</li><li>Ensure they do not run out of medication which could lead to trips to the drug store during a lockdown</li><li>Only take thyroid tests at standardized laboratories which adhere to COVID-19 safety guidelines</li><li>Visit clinics on time for appointments and carry along all required lab results</li><li>Take medications exactly as prescribed</li><li>If experiencing symptoms such as fever, cough, shortness of breath, contact the COVID-19 hotline and the endocrine clinic</li><li>Disclose your thyroid condition when speaking with the COVID-19 response team</li></ul><p><em>Reviewed by: Dr. Mrs. Josephine Akpalu, Head of Endocrine Unit, Korle-Bu Teaching Hospital.</em></p>',
                'status' => 'published',
                'published_at' => '2020-05-24',
            ],
            [
                'slug' => 'message-from-federal-president-covid19',
                'title' => 'Message From The Federal President — COVID-19',
                'excerpt' => 'Ashok Bhaseen, President of Thyroid Federation International, addresses pandemic concerns for thyroid patients worldwide.',
                'body' => '<p>In this tough and unprecedented time, there is vast amounts of information circulating about COVID-19, which may be overwhelming and confusing. Some thyroid patients have questions on how does this impact them.</p><p><strong>There is no evidence that patients with thyroid issues have something more to worry as compared to the rest of the population.</strong></p><p>What each one of us can do is take proper recommended measures that are advocated by the WHO and Health Authorities in each of your countries. Maintaining safe distance (social distancing), wearing face-mask, hygienic lifestyle — thoroughly washing hands will minimize the chances of exposure. Best is not to take any chances of socializing outside your house.</p><p>We also want to thank and salute the amazing healthcare and medical providers who have been working tirelessly in response to this pandemic — we appreciate your efforts to keep the people of your countries safe during this pandemic.</p><h3>Guidance</h3><ul><li>Avoid close contact with people and strangers not known to you</li><li>Avoid touching your eyes, nose, or mouth with unwashed hands</li><li>Wash your hands often with soap and water for at least 20 seconds</li><li>If soap and water are not readily available, use an alcohol-based hand sanitizer that contains at least 60% alcohol</li><li>It is especially important to clean hands after going to the bathroom, before eating, and after coughing, sneezing or blowing your nose</li><li>Avoid traveling if you are sick and also avoid crowded places</li></ul><p>Please keep and stay safe in these difficult times.</p><p><em>Ashok Bhaseen, M.Pharm, MMS — President, Thyroid Federation International</em></p>',
                'status' => 'published',
                'published_at' => '2020-05-24',
            ],
            [
                'slug' => 'maiden-patients-forum',
                'title' => 'Thyroid Ghana Foundation Holds Maiden Patients Forum',
                'excerpt' => 'The foundation organized its inaugural Thyroid Patients Forum on October 20, 2018 with consultants from Korle-Bu Teaching Hospital.',
                'body' => '<p>The Thyroid Patients Forum is a Thyroid Ghana Foundation initiative which aims to give thyroid patients an opportunity to interact with Consultants and Specialists from the College of Health Sciences and Korle-Bu Teaching Hospital who are involved in the diagnosis, management and treatment of thyroid cases.</p><p>The Founder and President of the Foundation, Mrs. Nana Adwoa Konadu Dsane, who had undergone surgery for a thyroid problem, chose to organize the maiden edition of the Thyroid Patients Forum on her birthday, 20th October, 2018 to share the special day with patients under the Foundation\'s Patient Support Programme.</p><blockquote><p>"There is very little information on thyroid disorders out there and this can cause a lot of panic among patients who already experience anxieties due to their condition and desperately need to know what steps to take to relieve the stress associated with the disease."</p></blockquote><p>She noted that patients who visit the clinics for treatment do not get enough opportunity to ask questions relating to their condition mainly because the clinics are very busy and time allocated per patient is very limited. She added that it was necessary to host the forum not only in English but also some local languages to cater for a wide category of patients.</p><p>The Forum was chaired by Rev. Prof. Patrick F. Ayeh-Kumi, Provost of the College of Health Sciences, University of Ghana and Chairman of the Management Board. He translated all discussions by the consultants in Ga and Akan for the benefit of the participants.</p><h3>Expert Presentations</h3><p><strong>Dr. Alfred Tetteh</strong> (aka Prof T), Consultant Surgeon at Department of Surgery, provided detailed explanations on what goes into thyroid surgery, what patients are required to do prior to the surgery and what to expect after.</p><p><strong>Dr. Mrs. Josephine Akpalu</strong>, Consultant Endocrinologist and Head of the Endocrine Unit, provided an overview of the thyroid gland and thyroid disease problems. She charged participants to test for thyroid disorders and advised on what steps to take if the test results are unfavorable.</p><p><strong>Dr. Naa Adorkor Aryeetey</strong>, Radiation Oncologist at the National Centre for Radiotherapy and Nuclear Medicine, talked about thyroid cancers and the use of radioactive iodine to treat hyperthyroidism. She encouraged the general public to seek earlier treatments for thyroid diseases as this can be crucial in preventing thyroid cancers.</p><p><strong>Mrs. Beatrice Williams</strong>, Clinical Psychologist, Department of Psychiatry, took the patients through a quick counseling session. She noted that being a thyroid patient can be very disheartening due to the fact that the condition may require lifelong treatment, and patients can easily become depressed.</p><p><strong>Ms. Portia Dzivenu</strong>, Dietician and Senior Research Assistant at the Department of Nutrition and Dietetics, gave a breakdown of foods that are healthy for both categories of thyroid disorders — hypothyroidism and hyperthyroidism.</p><p>The programme ended with a celebration of the Founder\'s birthday. She used the platform to encourage all participants to be ambassadors for thyroid health.</p><p><em>The Thyroid Patients Forum was proudly supported by Ernest Chemist, Kenzo\'s Place, Bedita Pharmacy Ltd, The College of Health Sciences, University of Ghana, Chartered Institute of Leadership and Governance, and the Dsane, Kumahlor, Pinkran and Burah Families.</em></p>',
                'status' => 'published',
                'published_at' => '2019-01-13',
            ],
            [
                'slug' => 'how-i-battled-cancer-jeremie-van-garshong',
                'title' => 'How I Battled Cancer — Jeremie Van-Garshong Reveals',
                'excerpt' => 'Live FM presenter Jeremie Van-Garshong reveals her decade-long thyroid cancer battle and miraculous recovery.',
                'body' => '<p>Radio and television presenter, Jeremie Van-Garshong cannot stop thanking God for healing her of the thyroid cancer she has been battling for the past 10 years. The Live FM presenter who has been off air for the past two months admitted that getting thyroid cancer has been the toughest challenge she ever had to face.</p><blockquote><p>"But God used my toughest challenge to bring me my biggest testimony ever and I can\'t wait to tell it all."</p></blockquote><p>Jeremie revealed that it was only her family and close friends who knew her struggle with cancer and how hard it was for her to pretend as if there was nothing wrong with her as she went about doing her duties as a radio and television presenter.</p><p>Her biggest fear was when doctors told her she could lose her voice in an operation in Germany because the surgery involved removing a tumor in her throat.</p><blockquote><p>"I lost hope because that was my career hanging on the line but as the doctors wheeled me into the theatre, I remember feeling a hand gently take my hand and walk alongside my bed. And as the two nurses began plugging machines and tubes into my body, I started praying saying \'Jesus stay with me and in my heart\'. I knew it was His hands that held mine."</p></blockquote><p>"After the surgery, my voice went for weeks and that got me scared because I thought I wouldn\'t be able to speak again and that was the end of my career. But thanks to God, I started speaking again and completely healed in the name of the Lord."</p><p>To her, the two months she was away in Germany has really brought her closer to God because she has come to accept that it is only God who can heal and not man.</p><p>The outspoken presenter said she is done with a song she composed during her trying moments because it is not everyone who gets lucky with such surgeries. She has also written a book titled <em>"Valley of the Shadow Of Death"</em> explaining in detail the struggles she went through battling with thyroid cancer and how she was finally saved by God.</p>',
                'status' => 'published',
                'published_at' => '2018-05-30',
            ],
            [
                'slug' => 'thyroid-disorders-central-ghana-iodization',
                'title' => 'Thyroid Disorders in Central Ghana: The Influence of 20 Years of Iodization',
                'excerpt' => 'Retrospective study of 10,484 thyroid cases reveals significant changes in thyroid disorder prevalence following Ghana\'s 1996 salt iodization program.',
                'body' => '<h3>Abstract</h3><p><strong>Background:</strong> Ghana began mandatory iodization of salt in 1996. This study compares the prevalence of thyroid disorders before and after the introduction of iodization.</p><p><strong>Methods:</strong> This is a retrospective study of thyroid cases from the middle belt of Ghana between 1982 and 2014. To demonstrate a link between iodization and hyperthyroidism and autoimmunity, we compared the prevalence of hyperthyroidism and autoimmune thyroid disorders before and after the iodization programme.</p><p><strong>Results:</strong> A total of 10,484 (7,548 females, 2,936 males) cases were recorded. The rate of thyroid cases seen was 343/100,000. Nontoxic nodular goiters (25.7%) and toxic nodular goiters (22.5%) represented the second commonest thyroid disorders recorded. The prevalence of hyperthyroid disorders seen after 1996 was significantly higher than the prevalence seen before the iodization (40.0 versus 21.1%, p &lt; 0.001). The prevalence of autoimmune disorders recorded after iodization was significantly higher than that before the iodization programme started (22.3% versus 9.6%, p &lt; 0.001).</p><p><strong>Conclusions:</strong> This study has revealed a significant increase in thyroid admissions in Central Ghana over the decades. A connection between iodine fortification and iodine-induced hyperthyroidism and between iodine fortification and autoimmune thyroiditis has been shown in this study.</p>',
                'status' => 'published',
                'published_at' => '2018-05-30',
            ],
            [
                'slug' => 'thyroid-disorders-accra-korle-bu-study',
                'title' => 'Thyroid Disorders in Accra: A Retrospective Histopathological Study at Korle-Bu Teaching Hospital',
                'excerpt' => 'A pathology department retrospective (2004-2010) analyzing 1,300 thyroid cases revealing an annual incidence of 185.7 cases.',
                'body' => '<p><em>EM Der, SE Quayson, JN Clegg-Lamptey, EK Wiredu, RKD Ephraim, RK Gyasi</em></p><h3>Abstract</h3><p>There is a scarcity of data on thyroid disorders in Ghana. This retrospective study examined the spectrum and incidence of thyroid disorders by reviewing all thyroid disorders reported in the Department of Pathology, Korle-Bu Teaching Hospital (KBTH) between 2004 and 2010.</p><p>1,300 (3.7%) cases were reported, representing an annual incidence of 185.7 cases. The ages ranged from 1-86 years with a mean of 41.5 (SD=13.9). Most cases — 353 (27.4%) — were between the 30-39 years age group. The majority, 1,141 (87.8%), were females.</p><p>The top eight common thyroid diseases were: non-toxic multinodular goitre 1,002 (77.5%), follicular adenoma 86 (6.6%), diffuse toxic goitre 42 (3.2%), papillary thyroid carcinoma 40 (3.1%), thyroglossal duct cyst 35 (2.7%), Hashimoto\'s thyroiditis 28 (2.2%), lymphocytic thyroiditis 22 (1.7%) and follicular carcinoma 17 (1.3%).</p><p>Sixty-six (43.4%) of the neoplastic thyroid disorders were malignant with a prevalence of 0.18 among thyroid samples and annual incidence of 9.40 cases. The commonest thyroid cancer was papillary carcinoma 40 (60.6%), with a mean age of 38.3 (SD=16.1) years; the majority, 34 (82.9%), were women.</p><p>A wide spectrum of thyroid disorders exists in Ghana, with an annual incidence of 185.7 cases. The commonest malignant thyroid disorder was papillary carcinoma, though iodine deficiency is endemic in Ghana and on this basis one would have expected follicular carcinoma to be the commonest thyroid cancer in Ghana.</p>',
                'status' => 'published',
                'published_at' => '2018-05-30',
            ],
        ];
    }
}
