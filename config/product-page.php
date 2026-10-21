<?php

declare(strict_types=1);

return [
    'brand' => [
        'name' => 'Unified Digital Workspace',
        'logo_text' => 'UW',
    ],

    'nav' => [
        'links' => [
            ['label' => 'Pricing', 'href' => '/product-template#pricing'],
            ['label' => 'Modules', 'href' => '/modules'],
            ['label' => 'Plan your workspace', 'href' => '/product-enterprise'],
        ],
        'cta_secondary' => ['label' => 'Sign in', 'href' => '/login'],
        'cta_primary' => ['label' => 'View pricing', 'href' => '/product-template#pricing'],
    ],

    'faqs' => [
        ['q' => 'Can I start with just a website?', 'a' => 'Yes. Website Essentials and Managed Business include CMS publishing, forms, hosting and a stated number of website editors. Operational modules and access can be added separately.'],
        ['q' => 'Do public visitors count as paid users?', 'a' => 'No. Public visitors and external customers are not staff seats. Website editors use their plan allowance; operational users are counted at their highest access level for the month.'],
        ['q' => 'What happens when I reach a usage limit?', 'a' => 'Contact us about a larger plan or an agreed capacity quote. The calculator does not add automatic website overage charges. External CDN and direct static delivery are outside application-served bandwidth controls.'],
        ['q' => 'Are setup, domains and taxes included?', 'a' => 'Standard setup is a separate one-time fee shown in the calculator. Domain registration, renewals, custom design, migration and mailbox hosting are separate. Applicable taxes and agreed services may be additional.'],
        ['q' => 'Will using the calculator change my billing?', 'a' => 'No. It only estimates costs. Existing customer agreements keep their agreed rates; a new agreement must be explicitly adopted before it controls billing.'],
        ['q' => 'How do I pay an invoice?', 'a' => 'Issued invoices include a private payment link. GHS invoices use secure Paystack checkout. After verified payment, the invoice is marked paid and a receipt is available. Payment reminders go to the organisation billing contact.'],
    ],

    'footer' => [
        'tagline' => 'Projects, documents, people and public websites in one workspace.',
        'columns' => [
            'Workspace' => [
                ['label' => 'Home', 'href' => '/'],
                ['label' => 'Modules', 'href' => '/modules'],
                ['label' => 'Pricing', 'href' => '/product-template#pricing'],
            ],
            'Get started' => [
                ['label' => 'Plan your workspace', 'href' => '/product-enterprise'],
                ['label' => 'Sign in', 'href' => '/login'],
            ],
        ],
    ],
];
