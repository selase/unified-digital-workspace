<?php

declare(strict_types=1);

return [
    'brand' => [
        'name' => 'StarterKit',
        'logo_text' => 'SK',
    ],

    'nav' => [
        'links' => [
            ['label' => 'Pricing', 'href' => '/product-template#pricing'],
            ['label' => 'Documentation', 'href' => '/product-docs'],
            ['label' => 'Enterprise', 'href' => '/product-enterprise'],
        ],
        'cta_secondary' => ['label' => 'Sign in', 'href' => '/login'],
        'cta_primary' => ['label' => 'Start for free', 'href' => '/register'],
    ],

    'hero' => [
        'title' => "Launch your next\nSaaS in days",
        'subtitle' => 'The ultimate Laravel starter kit with multi-tenancy, LLM integration, and premium Metronic styling.',
        'cta_primary' => ['label' => 'Get Started', 'href' => '/register'],
        'cta_secondary' => ['label' => 'View Demo', 'href' => '#'],
        'media' => [
            'type' => 'image',
            'src' => '/assets/img/marketing/hero.png',
            'alt' => 'App screenshot',
        ],
    ],

    'plans' => [
        [
            'name' => 'Starter',
            'description' => 'Perfect for side projects and small teams.',
            'monthly_price' => 10,
            'yearly_price' => 100,
            'most_popular' => false,
            'features' => [
                'Up to 3 projects',
                'Basic analytics',
                '24-hour retention',
                'Community support',
            ],
            'cta' => [
                'label' => 'Start for free',
                'href' => '/register?plan=starter',
            ],
        ],
        [
            'name' => 'Pro',
            'description' => 'Best for growing startups and active products.',
            'monthly_price' => 29,
            'yearly_price' => 290,
            'most_popular' => true,
            'features' => [
                'Unlimited projects',
                'Advanced performance metrics',
                '30-day retention',
                'Priority email support',
                'Custom alerts',
            ],
            'cta' => [
                'label' => 'Start 14-day trial',
                'href' => '/register?plan=pro',
            ],
        ],
        [
            'name' => 'Enterprise',
            'description' => 'Scale without limits. High-availability and security.',
            'monthly_price' => 99,
            'yearly_price' => 990,
            'most_popular' => false,
            'features' => [
                'Everything in Pro',
                'Unlimited retention',
                'SLA & dedicated support',
                'SSO & SAML integration',
                'Custom data residency',
            ],
            'cta' => [
                'label' => 'Contact Sales',
                'href' => '/contact',
            ],
        ],
    ],

    'intro' => [
        'eyebrow' => 'Onboarding',
        'title' => 'Ready-to-use business logic',
        'body' => 'Skip the boilerplate and focus on your core product. We handle the heavy lifting of multi-tenancy and user management.',
        'cards' => [
            ['kicker' => 'Tenancy', 'title' => 'Multi-tenant by default', 'body' => 'Each client gets their own space, subdomain, and isolated data.'],
            ['kicker' => 'Payments', 'title' => 'Stripe ready', 'body' => 'Integrated billing with per-tenant subscription management.'],
            ['kicker' => 'AI', 'title' => 'LLM Integration', 'body' => 'Native support for OpenAI, Anthropic, and Google Gemini.'],
        ],
    ],

    'capabilities' => [
        'title' => 'Everything you need to scale',
        'subtitle' => 'Highly modular architecture designed for high-performance and developer happiness.',
        'items' => [
            ['icon' => '↗', 'title' => 'Multilingual', 'body' => 'Full localization support for global audiences.'],
            ['icon' => '⌘', 'title' => 'Audit Trail', 'body' => 'Complete activity logs for every action in the system.'],
            ['icon' => '⚙', 'title' => 'Queues', 'body' => 'Tenant-aware background jobs and reliable scheduling.'],
            ['icon' => '⌁', 'title' => 'Health Checks', 'body' => 'Built-in monitoring for CPU, database, and system status.'],
            ['icon' => '✉', 'title' => 'Mail & Notifications', 'body' => 'Transactional emails and real-time alerts.'],
            ['icon' => '✈', 'title' => 'Storage', 'body' => 'Isolated storage foundations for S3 or local drivers.'],
        ],
    ],

    'testimonials' => [
        [
            'quote' => 'This starter kit saved us at least 3 months of development time. The multi-tenancy implementation is flawless.',
            'name' => 'Alex Rivera',
            'role' => 'CEO',
            'company' => 'CloudFlow',
        ],
        [
            'quote' => 'The best Laravel boilerplate I have ever used. The attention to detail in the Metronic integration is impressive.',
            'name' => 'Sarah Chen',
            'role' => 'Fullstack Developer',
            'company' => 'DevEngine',
        ],
    ],

    'deep_sections' => [
        [
            'eyebrow' => 'Management',
            'title' => 'Granular control over organizations',
            'body' => 'Manage tenants, users, and roles with ease via the comprehensive landlord and tenant admin panels.',
            'features' => [
                ['title' => 'Package Management', 'title' => 'Custom Packages', 'body' => 'Define what features and limits each plan gets.'],
                ['title' => 'Branding', 'title' => 'Tenant Branding', 'body' => 'Custom logos and primary colors for every organization.'],
                ['title' => 'IP Whitelisting', 'title' => 'Enhanced Security', 'body' => 'Restrict access based on IP or specific LLM models.'],
            ],
            'media' => [
                'type' => 'image',
                'src' => '/assets/img/marketing/rbac.png',
                'alt' => 'Admin dashboard',
            ],
        ],
    ],

    'faqs' => [
        ['q' => 'Is it compatible with Laravel 12?', 'a' => 'Yes, it is built on the latest version of Laravel and follows modern best practices.'],
        ['q' => 'Can I use it for single-tenant apps?', 'a' => 'While optimized for multi-tenancy, you can easily use it for a single-tenant application by setting up one default tenant.'],
    ],

    'final_cta' => [
        'title' => 'Ready to launch your vision?',
        'subtitle' => 'Join hundreds of developers building the future with StarterKit.',
        'cta_primary' => ['label' => 'Get Started', 'href' => '/register'],
        'cta_secondary' => ['label' => 'Contact Sales', 'href' => '/contact'],
    ],

    'footer' => [
        'tagline' => 'Building the future of SaaS, one project at a time.',
        'columns' => [
            'Product' => [
                ['label' => 'Pricing', 'href' => '/product-template#pricing'],
                ['label' => 'Documentation', 'href' => '/product-docs'],
                ['label' => 'Enterprise', 'href' => '/product-enterprise'],
            ],
            'Company' => [
                ['label' => 'About Us', 'href' => '#'],
                ['label' => 'Terms', 'href' => '/terms'],
                ['label' => 'Privacy', 'href' => '/privacy'],
            ],
        ],
    ],
];
