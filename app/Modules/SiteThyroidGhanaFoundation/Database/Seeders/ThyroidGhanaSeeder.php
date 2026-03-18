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

        $articles = [
            [
                'slug' => 'ghana-health-awards-honors-launched',
                'title' => 'Ghana Health Awards and Honors Launched',
                'excerpt' => 'Akoma Productions unveiled the inaugural Ghana Health Awards and Honors, recognizing healthcare professionals advancing Ghana\'s health sector.',
                'body' => '<p>Akoma Productions unveiled the inaugural Ghana Health Awards and Honors on November 30, 2022, at the Medical Training and Simulation Centre in Legon. The event recognized healthcare professionals and organizations advancing Ghana\'s health sector.</p><p>Founder Nana Adwoa Konadu Dsane emphasized: "Health workers mostly boil the ocean to save lives hence the need to acknowledge their sacrifices."</p><p>The ceremony detailed multiple award categories spanning healthcare professionals including surgeons, nurses, and dentists, as well as administrative roles and health facilities, with nominations opening at akomaproductionsgh.com.</p>',
                'status' => 'published',
                'published_at' => '2022-12-23',
            ],
            [
                'slug' => 'founder-featured-humble-beginnings',
                'title' => 'Nana Adwoa Konadu Dsane — Humble Beginning Stories Feature',
                'excerpt' => 'An extended profile detailing the founder\'s personal health journey and what inspired the creation of the Thyroid Ghana Foundation.',
                'body' => '<p>An extended profile has been published detailing Mrs. Dsane\'s personal health journey. She discovered hyperthyroidism after experiencing unexplained abdominal pain and recurring sore throat following childbirth in Ghana.</p><p>Despite working at Korle-Bu Teaching Hospital for over 16 years, she initially lacked awareness of thyroid disorders. After eight months of misdiagnosis, she underwent successful thyroid surgery.</p><p>She established the Thyroid Ghana Foundation to combat awareness gaps and provide affordable treatment access. The article highlights initial obstacles including patient denial, funding constraints, and institutional engagement, which she addressed through support platforms, patient forums, and partnerships with laboratories and hospitals offering discounted services.</p>',
                'status' => 'published',
                'published_at' => '2022-07-13',
            ],
            [
                'slug' => 'world-thyroid-awareness-week-launch',
                'title' => 'Launch of the 14th World Thyroid Awareness Week',
                'excerpt' => 'The foundation launched World Thyroid Awareness Week with the theme "It\'s Not You, It\'s Your Thyroid" in collaboration with UGMC.',
                'body' => '<p>The foundation launched World Thyroid Awareness Week with the theme "Thyroid and Communication — It\'s Not You, It\'s Your Thyroid" in collaboration with the University of Ghana Medical Centre.</p><p>The event featured photographic documentation of the celebration and announced the second phase of subsidized thyroid surgery initiatives for eligible patients. Free screenings were provided at community health centers across Accra.</p>',
                'status' => 'published',
                'published_at' => '2022-06-15',
            ],
            [
                'slug' => 'thyroid-disease-and-coronavirus',
                'title' => 'Thyroid Disease and Coronavirus',
                'excerpt' => 'Comprehensive pandemic guidance for thyroid patients — autoimmune thyroid disease does not increase COVID-19 risk.',
                'body' => '<p>The foundation has issued comprehensive pandemic guidance for thyroid patients. We can confirm that autoimmune thyroid disease does not increase COVID-19 risk, as thyroid patients remain immunocompetent.</p><p>Standard thyroid medications including levothyroxine, carbimazole, and propylthiouracil do not suppress immunity.</p><p>Safety protocols include continued medication adherence, avoiding supply shortages, utilizing certified laboratories, and contacting healthcare providers if experiencing fever, cough, or shortness of breath.</p><p><em>Reviewed by Dr. Josephine Akpalu, Head of Endocrine Unit, Korle-Bu Teaching Hospital.</em></p>',
                'status' => 'published',
                'published_at' => '2020-05-24',
            ],
            [
                'slug' => 'maiden-patients-forum',
                'title' => 'Thyroid Ghana Foundation Holds Maiden Patients Forum',
                'excerpt' => 'The foundation organized its inaugural Thyroid Patients Forum featuring consultants from Korle-Bu Teaching Hospital.',
                'body' => '<p>The foundation organized its inaugural Thyroid Patients Forum on October 20, 2018. The event featured consultants from Korle-Bu Teaching Hospital and University of Ghana addressing thyroid surgery, endocrinology, radiotherapy, psychology, and dietary management.</p><p>Dr. Josephine Akpalu discussed a thyroid gland overview; Dr. Alfred Tetteh detailed surgical procedures; Dr. Naa Adorkor Aryeetey covered thyroid cancer treatment; Beatrice Williams provided psychological support; and Portia Dzivenu addressed dietary concerns.</p><p>The informal setting accommodated local language translations in Ga and Akan, ensuring accessibility for all attendees.</p>',
                'status' => 'published',
                'published_at' => '2019-01-13',
            ],
            [
                'slug' => 'how-i-battled-cancer-jeremie-van-garshong',
                'title' => 'How I Battled Cancer — Jeremie Van-Garshong Reveals',
                'excerpt' => 'Live FM presenter Jeremie Van-Garshong shares her decade-long thyroid cancer battle and recovery journey.',
                'body' => '<p>Live FM radio and television presenter Jeremie Van-Garshong has disclosed her decade-long thyroid cancer battle. Following surgery in Germany involving tumor removal from her throat, she feared permanent voice loss but recovered fully.</p><p>"After the surgery, my voice went for weeks... But thanks to God, I started speaking again," she shared.</p><p>She composed a song during recovery and authored "Valley of the Shadow Of Death," detailing her medical journey and spiritual healing. Her story highlights the importance of early detection and the possibility of full recovery from thyroid cancer.</p>',
                'status' => 'published',
                'published_at' => '2018-05-30',
            ],
            [
                'slug' => 'thyroid-disorders-central-ghana-iodization',
                'title' => 'Thyroid Disorders in Central Ghana: The Influence of 20 Years of Iodization',
                'excerpt' => 'Research reveals that Ghana\'s 1996 salt iodization program changed the thyroid disease landscape significantly.',
                'body' => '<p>A retrospective research study comparing thyroid disorder prevalence before and after Ghana\'s 1996 mandatory salt iodization program has revealed significant findings.</p><p>Analysis of 10,484 cases (7,548 female; 2,936 male) across 1982-2014 showed that hyperthyroid disorder prevalence increased from 21.1% pre-1996 to 40.0% post-iodization. Autoimmune thyroid disorders rose from 9.6% to 22.3%.</p><p>Nontoxic nodular goiters (25.7%) and toxic nodular goiters (22.5%) represented the second and third most common presentations, establishing a connection between iodine fortification and iodine-induced hyperthyroidism.</p>',
                'status' => 'published',
                'published_at' => '2018-05-30',
            ],
            [
                'slug' => 'thyroid-disorders-accra-korle-bu-study',
                'title' => 'Thyroid Disorders in Accra: A Retrospective Study at Korle-Bu Teaching Hospital',
                'excerpt' => 'A pathology department retrospective (2004-2010) analyzing 1,300 thyroid cases with 185.7 annual incidence.',
                'body' => '<p>A pathology department retrospective study spanning 2004-2010 analyzed 1,300 thyroid cases at Korle-Bu Teaching Hospital, revealing an annual incidence of 185.7 cases.</p><p>Subjects averaged 41.5 years (range 1-86) and 87.8% were female. Primary diagnoses included nontoxic multinodular goiter (77.5%), follicular adenoma (6.6%), diffuse toxic goiter (3.2%), and papillary thyroid carcinoma (3.1%).</p><p>Of neoplastic cases, 43.4% proved malignant, with papillary carcinoma predominating at 60.6% — contradicting expectations of follicular carcinoma prevalence given Ghana\'s endemic iodine deficiency. This study underscores the need for improved diagnostic infrastructure.</p>',
                'status' => 'published',
                'published_at' => '2018-05-30',
            ],
        ];

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
}
