<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Modules\HrmsCore\Models\Organization\Center;
use App\Modules\HrmsCore\Models\Organization\Department;
use App\Modules\HrmsCore\Models\Organization\DepartmentType;
use App\Modules\HrmsCore\Models\Organization\Directorate;
use App\Modules\HrmsCore\Models\Organization\Grade;
use App\Modules\HrmsCore\Models\Organization\Unit;
use App\Services\Tenancy\TenantContext;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create([
        'name' => 'HRMS Org Test Company',
        'slug' => 'hrms-org-test-company',
    ]);

    app(TenantContext::class)->setTenant($this->tenant);
});

describe('Grade Model', function (): void {
    test('can create a grade', function (): void {
        $grade = Grade::create([
            'name' => 'Director',
            'description' => 'Top management level',
            'can_recommend_leave' => true,
            'can_approve_leave' => true,
            'can_appraise' => true,
            'sort_order' => 1,
        ]);

        expect($grade->id)->not->toBeNull()
            ->and($grade->uuid)->not->toBeNull()
            ->and($grade->tenant_id)->toBe($this->tenant->id)
            ->and($grade->name)->toBe('Director')
            ->and($grade->slug)->toBe('director')
            ->and($grade->can_recommend_leave)->toBeTrue()
            ->and($grade->can_approve_leave)->toBeTrue()
            ->and($grade->can_appraise)->toBeTrue();
    });

    test('grade generates slug automatically', function (): void {
        $grade = Grade::create([
            'name' => 'Assistant Director',
        ]);

        expect($grade->slug)->toBe('assistant-director');
    });

    test('grade is scoped to tenant', function (): void {
        Grade::create(['name' => 'Grade A']);

        $otherTenant = Tenant::factory()->create();

        // Switch context to other tenant
        app()->singleton(TenantContext::class, function () use ($otherTenant) {
            $context = new TenantContext();
            $context->setTenant($otherTenant);

            return $context;
        });

        Grade::create(['name' => 'Grade B']);

        // Switch back to original tenant
        app()->singleton(TenantContext::class, function () {
            $context = new TenantContext();
            $context->setTenant($this->tenant);

            return $context;
        });

        // Should only see grades for the original tenant
        expect(Grade::count())->toBe(1);
        expect(Grade::first()->name)->toBe('Grade A');
    });

    test('can find grade by uuid', function (): void {
        $grade = Grade::create(['name' => 'Senior Officer']);

        $found = Grade::byUuid($grade->uuid)->first();

        expect($found->id)->toBe($grade->id);
    });
});

describe('Department Model', function (): void {
    test('can create a department', function (): void {
        $department = Department::create([
            'name' => 'Human Resources',
            'description' => 'Manages employee affairs',
        ]);

        expect($department->id)->not->toBeNull()
            ->and($department->uuid)->not->toBeNull()
            ->and($department->tenant_id)->toBe($this->tenant->id)
            ->and($department->name)->toBe('Human Resources')
            ->and($department->slug)->toBe('human-resources')
            ->and($department->is_active)->toBeTrue();
    });

    test('department has department types', function (): void {
        $department = Department::create(['name' => 'Finance']);

        DepartmentType::create([
            'department_id' => $department->id,
            'name' => 'Accounts Payable',
        ]);

        DepartmentType::create([
            'department_id' => $department->id,
            'name' => 'Accounts Receivable',
        ]);

        expect($department->departmentTypes)->toHaveCount(2);
    });

    test('active scope filters correctly', function (): void {
        Department::create(['name' => 'Active Dept', 'is_active' => true]);
        Department::create(['name' => 'Inactive Dept', 'is_active' => false]);

        expect(Department::active()->count())->toBe(1);
        expect(Department::count())->toBe(2);
    });
});

describe('DepartmentType Model', function (): void {
    test('can create department type with parent department', function (): void {
        $department = Department::create(['name' => 'IT']);

        $type = DepartmentType::create([
            'department_id' => $department->id,
            'name' => 'Software Development',
            'description' => 'Develops software solutions',
        ]);

        expect($type->id)->not->toBeNull()
            ->and($type->department_id)->toBe($department->id)
            ->and($type->department->name)->toBe('IT')
            ->and($type->slug)->toBe('software-development');
    });
});

describe('Directorate Model', function (): void {
    test('can create a directorate', function (): void {
        $directorate = Directorate::create([
            'name' => 'Corporate Services',
            'description' => 'Oversees administrative functions',
        ]);

        expect($directorate->id)->not->toBeNull()
            ->and($directorate->uuid)->not->toBeNull()
            ->and($directorate->tenant_id)->toBe($this->tenant->id)
            ->and($directorate->name)->toBe('Corporate Services')
            ->and($directorate->slug)->toBe('corporate-services');
    });
});

describe('Center Model', function (): void {
    test('can create a center with location', function (): void {
        $center = Center::create([
            'name' => 'Head Office',
            'location' => 'Accra, Greater Accra',
            'description' => 'Main headquarters',
        ]);

        expect($center->id)->not->toBeNull()
            ->and($center->location)->toBe('Accra, Greater Accra')
            ->and($center->slug)->toBe('head-office');
    });
});

describe('Unit Model', function (): void {
    test('can create a unit', function (): void {
        $unit = Unit::create([
            'name' => 'Procurement Unit',
            'description' => 'Handles purchasing',
        ]);

        expect($unit->id)->not->toBeNull()
            ->and($unit->uuid)->not->toBeNull()
            ->and($unit->tenant_id)->toBe($this->tenant->id)
            ->and($unit->name)->toBe('Procurement Unit')
            ->and($unit->slug)->toBe('procurement-unit');
    });
});

describe('HasHrmsUuid Trait', function (): void {
    test('uses uuid as route key', function (): void {
        $grade = Grade::create(['name' => 'Test Grade']);

        expect($grade->getRouteKeyName())->toBe('uuid');
    });

    test('uuid is generated on create', function (): void {
        $grade = Grade::create(['name' => 'Another Grade']);

        expect($grade->uuid)->not->toBeNull()
            ->and(mb_strlen($grade->uuid))->toBe(36); // UUID format
    });
});
