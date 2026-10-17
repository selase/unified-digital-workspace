<?php

declare(strict_types=1);

return [
    'name' => 'Forms & Surveys',
    'slug' => 'forms',
    'description' => 'General form builder, HR employee request forms, multi-question surveys, and live polls.',
    'version' => '1.0.0',
    'provider' => App\Modules\Forms\Providers\FormsServiceProvider::class,
    'permissions' => [
        'forms.view',
        'forms.create',
        'forms.manage',
        'forms.submit',
        'forms.review',
    ],
];
