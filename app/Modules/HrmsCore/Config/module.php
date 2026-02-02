<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Module Information
    |--------------------------------------------------------------------------
    */
    'name' => 'HRMS Core',
    'slug' => 'hrms-core',
    'version' => '1.0.0',
    'description' => 'Human Resource Management System - Core module providing employee management, leave tracking, appraisals, promotions, and recruitment.',

    /*
    |--------------------------------------------------------------------------
    | Module Namespace & Provider
    |--------------------------------------------------------------------------
    */
    'namespace' => 'App\\Modules\\HrmsCore',
    'provider' => 'App\\Modules\\HrmsCore\\Providers\\HrmsCoreServiceProvider',

    /*
    |--------------------------------------------------------------------------
    | Pricing & Billing
    |--------------------------------------------------------------------------
    */
    'tier' => 'professional',
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
    | Features provided by this module. These are synced to the tenant's
    | feature table when the module is enabled.
    */
    'features' => [
        // Employee Management Features
        'hrms.employees.manage' => [
            'type' => 'boolean',
            'name' => 'Employee Management',
            'description' => 'Create and manage employee records',
        ],
        'hrms.employees.limit' => [
            'type' => 'numeric',
            'name' => 'Employee Limit',
            'description' => 'Maximum number of employees',
            'default' => 50,
        ],

        // Organization Structure
        'hrms.organization.manage' => [
            'type' => 'boolean',
            'name' => 'Organization Structure',
            'description' => 'Manage departments, units, and organizational hierarchy',
        ],

        // Leave Management
        'hrms.leave.annual' => [
            'type' => 'boolean',
            'name' => 'Annual Leave Management',
            'description' => 'Process annual leave requests with approval workflow',
        ],
        'hrms.leave.other' => [
            'type' => 'boolean',
            'name' => 'Other Leave Types',
            'description' => 'Manage sick leave, maternity, study leave, etc.',
        ],

        // Salary Management
        'hrms.salary.manage' => [
            'type' => 'boolean',
            'name' => 'Salary Management',
            'description' => 'Manage salary levels, steps, and allowances',
        ],

        // Appraisal System
        'hrms.appraisal.enabled' => [
            'type' => 'boolean',
            'name' => 'Performance Appraisals',
            'description' => 'Employee performance evaluation system',
        ],

        // Promotion System
        'hrms.promotion.enabled' => [
            'type' => 'boolean',
            'name' => 'Staff Promotions',
            'description' => 'Multi-step promotion request workflow',
        ],

        // Recruitment
        'hrms.recruitment.enabled' => [
            'type' => 'boolean',
            'name' => 'Recruitment Module',
            'description' => 'Job postings and applicant tracking',
        ],
        'hrms.recruitment.public_portal' => [
            'type' => 'boolean',
            'name' => 'Public Job Portal',
            'description' => 'Allow public access to job listings',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    | Permissions defined by this module. These are seeded into the
    | permissions table when the module is installed.
    */
    'permissions' => [
        // Employee Permissions
        'hrms.employees.view',
        'hrms.employees.create',
        'hrms.employees.update',
        'hrms.employees.delete',
        'hrms.employees.export',

        // Organization Permissions
        'hrms.departments.view',
        'hrms.departments.manage',
        'hrms.department_types.view',
        'hrms.department_types.manage',
        'hrms.directorates.view',
        'hrms.directorates.manage',
        'hrms.units.view',
        'hrms.units.manage',
        'hrms.centers.view',
        'hrms.centers.manage',
        'hrms.grades.view',
        'hrms.grades.manage',

        // Leave Permissions
        'hrms.leave.view',
        'hrms.leave.request',
        'hrms.leave.recommend',
        'hrms.leave.approve',
        'hrms.leave.manage_categories',
        'hrms.leave.manage_holidays',

        // Salary Permissions
        'hrms.salary.view',
        'hrms.salary.manage',
        'hrms.allowances.view',
        'hrms.allowances.manage',

        // Appraisal Permissions
        'hrms.appraisal.view',
        'hrms.appraisal.create',
        'hrms.appraisal.evaluate',
        'hrms.appraisal.manage',

        // Promotion Permissions
        'hrms.promotion.view',
        'hrms.promotion.request',
        'hrms.promotion.recommend',
        'hrms.promotion.approve',

        // Recruitment Permissions
        'hrms.jobs.view',
        'hrms.jobs.create',
        'hrms.jobs.manage',
        'hrms.applications.view',
        'hrms.applications.manage',
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
