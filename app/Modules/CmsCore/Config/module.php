<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Module Information
    |--------------------------------------------------------------------------
    */
    'name' => 'CMS Core',
    'slug' => 'cms-core',
    'version' => '1.0.0',
    'description' => 'CMS core schema for posts, post types, taxonomies, media, and settings.',

    /*
    |--------------------------------------------------------------------------
    | Module Namespace & Provider
    |--------------------------------------------------------------------------
    */
    'namespace' => 'App\\Modules\\CmsCore',
    'provider' => 'App\\Modules\\CmsCore\\Providers\\CmsCoreServiceProvider',

    /*
    |--------------------------------------------------------------------------
    | Pricing & Billing
    |--------------------------------------------------------------------------
    */
    'tier' => 'standard',
    'is_billable' => true,

    /*
    |--------------------------------------------------------------------------
    | Dependencies & Conflicts
    |--------------------------------------------------------------------------
    */
    'depends_on' => ['core'],
    'conflicts_with' => [],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    'features' => [
        'cms.posts.manage' => [
            'type' => 'boolean',
            'name' => 'Post Management',
            'description' => 'Create and manage posts across post types',
        ],
        'cms.taxonomies.manage' => [
            'type' => 'boolean',
            'name' => 'Taxonomy Management',
            'description' => 'Manage categories and tags',
        ],
        'cms.media.manage' => [
            'type' => 'boolean',
            'name' => 'Media Library',
            'description' => 'Upload and manage media assets',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    */
    'permissions' => [
        'cms.posts.view',
        'cms.posts.create',
        'cms.posts.update',
        'cms.posts.delete',

        'cms.post_types.view',
        'cms.post_types.manage',

        'cms.categories.view',
        'cms.categories.manage',

        'cms.tags.view',
        'cms.tags.manage',

        'cms.media.view',
        'cms.media.manage',

        'cms.menus.view',
        'cms.menus.manage',

        'cms.settings.view',
        'cms.settings.manage',
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    */
    'routes' => [
        'web' => true,
        'api' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Module Metadata
    |--------------------------------------------------------------------------
    */
    'author' => 'UDW Team',
    'homepage' => null,
    'support' => null,
];
