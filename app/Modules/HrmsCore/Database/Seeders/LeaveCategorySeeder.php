<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Database\Seeders;

use App\Modules\HrmsCore\Models\Leave\LeaveCategory;
use Illuminate\Database\Seeder;

/**
 * Seeder for leave categories.
 *
 * Seeds default leave categories for a tenant.
 */
final class LeaveCategorySeeder extends Seeder
{
    /**
     * Default leave categories with their properties.
     *
     * @var array<int, array{name: string, default_days: int, description: string, is_paid: bool, requires_documentation: bool}>
     */
    private const CATEGORIES = [
        [
            'name' => 'Annual Leave',
            'default_days' => 21,
            'description' => 'Standard annual leave entitlement for all employees.',
            'is_paid' => true,
            'requires_documentation' => false,
        ],
        [
            'name' => 'Sick Leave',
            'default_days' => 30,
            'description' => 'Leave for illness or medical treatment. Medical certificate required for extended periods.',
            'is_paid' => true,
            'requires_documentation' => true,
        ],
        [
            'name' => 'Maternity Leave',
            'default_days' => 90,
            'description' => 'Leave for female employees before and after childbirth.',
            'is_paid' => true,
            'requires_documentation' => true,
        ],
        [
            'name' => 'Paternity Leave',
            'default_days' => 5,
            'description' => 'Leave for male employees following the birth of their child.',
            'is_paid' => true,
            'requires_documentation' => true,
        ],
        [
            'name' => 'Study Leave',
            'default_days' => 365,
            'description' => 'Leave for employees pursuing further education or professional development.',
            'is_paid' => false,
            'requires_documentation' => true,
        ],
        [
            'name' => 'Compassionate Leave',
            'default_days' => 5,
            'description' => 'Leave for bereavement or family emergencies.',
            'is_paid' => true,
            'requires_documentation' => false,
        ],
        [
            'name' => 'Casual Leave',
            'default_days' => 5,
            'description' => 'Short-term leave for personal matters.',
            'is_paid' => true,
            'requires_documentation' => false,
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::CATEGORIES as $index => $category) {
            LeaveCategory::firstOrCreate(
                ['name' => $category['name']],
                [
                    'default_days' => $category['default_days'],
                    'description' => $category['description'],
                    'is_paid' => $category['is_paid'],
                    'requires_documentation' => $category['requires_documentation'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ]
            );
        }
    }
}
