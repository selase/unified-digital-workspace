<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Modules\HrmsCore\Enums\Gender;
use App\Modules\HrmsCore\Enums\MaritalStatus;
use App\Modules\HrmsCore\Models\Employees\BankDetails;
use App\Modules\HrmsCore\Models\Employees\Children;
use App\Modules\HrmsCore\Models\Employees\CurrentJob;
use App\Modules\HrmsCore\Models\Employees\EducationalBackground;
use App\Modules\HrmsCore\Models\Employees\EmergencyContact;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Models\Employees\EmployeeParent;
use App\Modules\HrmsCore\Models\Employees\EmploymentStatus;
use App\Modules\HrmsCore\Models\Employees\NextOfKin;
use App\Modules\HrmsCore\Models\Employees\PreviousWorkExperience;
use App\Modules\HrmsCore\Models\Employees\ProfessionalQualification;
use App\Modules\HrmsCore\Models\Organization\Center;
use App\Modules\HrmsCore\Models\Organization\Department;
use App\Modules\HrmsCore\Models\Organization\DepartmentType;
use App\Modules\HrmsCore\Models\Organization\Directorate;
use App\Modules\HrmsCore\Models\Organization\Grade;
use App\Modules\HrmsCore\Models\Organization\Unit;
use App\Services\Tenancy\TenantContext;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create([
        'name' => 'Employee Test Company',
        'slug' => 'employee-test-company',
    ]);

    app(TenantContext::class)->setTenant($this->tenant);
});

describe('Employee Model', function (): void {
    test('can create an employee with minimal data', function (): void {
        $employee = Employee::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
        ]);

        expect($employee->id)->not->toBeNull()
            ->and($employee->uuid)->not->toBeNull()
            ->and($employee->tenant_id)->toBe($this->tenant->id)
            ->and($employee->first_name)->toBe('John')
            ->and($employee->last_name)->toBe('Doe')
            ->and($employee->is_active)->toBeTrue();
    });

    test('can create an employee with full personal details', function (): void {
        $employee = Employee::create([
            'employee_staff_id' => 'EMP-001',
            'cagd_staff_id' => 'CAGD-12345',
            'file_number' => 'FN-001',
            'title' => 'Mr.',
            'first_name' => 'Kwame',
            'middle_name' => 'Asante',
            'last_name' => 'Mensah',
            'gender' => Gender::Male,
            'date_of_birth' => '1985-05-15',
            'nationality' => 'Ghanaian',
            'marital_status' => MaritalStatus::Married,
            'email' => 'kwame.mensah@example.com',
            'mobile' => '+233244123456',
            'residential_address' => 'Accra, Greater Accra',
            'social_security_number' => 'SSN-123456789',
        ]);

        expect($employee->title)->toBe('Mr.')
            ->and($employee->middle_name)->toBe('Asante')
            ->and($employee->gender)->toBe(Gender::Male)
            ->and($employee->marital_status)->toBe(MaritalStatus::Married)
            ->and($employee->date_of_birth->format('Y-m-d'))->toBe('1985-05-15');
    });

    test('displayName returns first and last name', function (): void {
        $employee = Employee::create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
        ]);

        expect($employee->displayName())->toBe('Jane Smith');
    });

    test('fullName includes title and middle name', function (): void {
        $employee = Employee::create([
            'title' => 'Dr.',
            'first_name' => 'Ama',
            'middle_name' => 'Serwaa',
            'last_name' => 'Boateng',
        ]);

        expect($employee->fullName())->toBe('Dr. Ama Serwaa Boateng');
    });

    test('active scope filters correctly', function (): void {
        Employee::create(['first_name' => 'Active', 'last_name' => 'Employee', 'is_active' => true]);
        Employee::create(['first_name' => 'Inactive', 'last_name' => 'Employee', 'is_active' => false]);

        expect(Employee::active()->count())->toBe(1);
        expect(Employee::count())->toBe(2);
    });

    test('employee is scoped to tenant', function (): void {
        Employee::create(['first_name' => 'Tenant1', 'last_name' => 'Employee']);

        $otherTenant = Tenant::factory()->create();

        app()->singleton(TenantContext::class, function () use ($otherTenant) {
            $context = new TenantContext();
            $context->setTenant($otherTenant);

            return $context;
        });

        Employee::create(['first_name' => 'Tenant2', 'last_name' => 'Employee']);

        app()->singleton(TenantContext::class, function () {
            $context = new TenantContext();
            $context->setTenant($this->tenant);

            return $context;
        });

        expect(Employee::count())->toBe(1);
        expect(Employee::first()->first_name)->toBe('Tenant1');
    });

    test('soft deletes work correctly', function (): void {
        $employee = Employee::create(['first_name' => 'Test', 'last_name' => 'Delete']);
        $employee->delete();

        expect(Employee::count())->toBe(0);
        expect(Employee::withTrashed()->count())->toBe(1);
    });
});

describe('Employee Factory', function (): void {
    test('can create employee using factory', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();

        expect($employee->id)->not->toBeNull()
            ->and($employee->first_name)->not->toBeNull()
            ->and($employee->last_name)->not->toBeNull()
            ->and($employee->email)->not->toBeNull()
            ->and($employee->tenant_id)->toBe($this->tenant->id);
    });

    test('factory male state works', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->male()->create();

        expect($employee->gender)->toBe(Gender::Male)
            ->and($employee->maiden_name)->toBeNull();
    });

    test('factory female state works', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->female()->create();

        expect($employee->gender)->toBe(Gender::Female);
    });

    test('factory married state works', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->married()->create();

        expect($employee->marital_status)->toBe(MaritalStatus::Married)
            ->and($employee->name_of_spouse)->not->toBeNull()
            ->and($employee->spouse_phone_number)->not->toBeNull();
    });

    test('factory withChildren state works', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->withChildren(3)->create();

        expect($employee->is_any_children)->toBeTrue()
            ->and($employee->number_of_children)->toBe(3);
    });
});

describe('Employee Organization Relationships', function (): void {
    test('employee belongs to a grade', function (): void {
        $grade = Grade::create(['name' => 'Director', 'sort_order' => 1]);
        $employee = Employee::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'grade_id' => $grade->id,
        ]);

        expect($employee->grade->name)->toBe('Director');
    });

    test('employee belongs to a center', function (): void {
        $center = Center::create(['name' => 'Head Office']);
        $employee = Employee::create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'center_id' => $center->id,
        ]);

        expect($employee->center->name)->toBe('Head Office');
    });

    test('employee belongs to many departments', function (): void {
        $employee = Employee::create(['first_name' => 'Test', 'last_name' => 'Employee']);

        $dept1 = Department::create(['name' => 'HR']);
        $dept2 = Department::create(['name' => 'Finance']);

        $employee->departments()->attach([$dept1->id, $dept2->id]);

        expect($employee->departments)->toHaveCount(2);
    });

    test('employee belongs to many directorates', function (): void {
        $employee = Employee::create(['first_name' => 'Test', 'last_name' => 'Employee']);

        $dir1 = Directorate::create(['name' => 'Corporate Services']);
        $dir2 = Directorate::create(['name' => 'Operations']);

        $employee->directorates()->attach([$dir1->id, $dir2->id]);

        expect($employee->directorates)->toHaveCount(2);
    });

    test('employee belongs to many units', function (): void {
        $employee = Employee::create(['first_name' => 'Test', 'last_name' => 'Employee']);

        $unit1 = Unit::create(['name' => 'Procurement']);
        $unit2 = Unit::create(['name' => 'IT Support']);

        $employee->units()->attach([$unit1->id, $unit2->id]);

        expect($employee->units)->toHaveCount(2);
    });

    test('employee belongs to many department types', function (): void {
        $employee = Employee::create(['first_name' => 'Test', 'last_name' => 'Employee']);

        $dept = Department::create(['name' => 'IT']);
        $type1 = DepartmentType::create(['department_id' => $dept->id, 'name' => 'Development']);
        $type2 = DepartmentType::create(['department_id' => $dept->id, 'name' => 'Support']);

        $employee->departmentTypes()->attach([$type1->id, $type2->id]);

        expect($employee->departmentTypes)->toHaveCount(2);
    });
});

describe('Employee Detail Relationships', function (): void {
    test('employee has one parent record', function (): void {
        $employee = Employee::create(['first_name' => 'Test', 'last_name' => 'Employee']);

        EmployeeParent::create([
            'employee_id' => $employee->id,
            'father_name' => 'John Senior',
            'father_alive' => true,
            'mother_name' => 'Jane Senior',
            'mother_alive' => true,
        ]);

        expect($employee->parent)->not->toBeNull()
            ->and($employee->parent->father_name)->toBe('John Senior');
    });

    test('employee has many children', function (): void {
        $employee = Employee::create([
            'first_name' => 'Test',
            'last_name' => 'Employee',
            'is_any_children' => true,
            'number_of_children' => 2,
        ]);

        Children::create(['employee_id' => $employee->id, 'name' => 'Child 1', 'gender' => Gender::Male]);
        Children::create(['employee_id' => $employee->id, 'name' => 'Child 2', 'gender' => Gender::Female]);

        expect($employee->children)->toHaveCount(2);
    });

    test('employee has one next of kin', function (): void {
        $employee = Employee::create(['first_name' => 'Test', 'last_name' => 'Employee']);

        NextOfKin::create([
            'employee_id' => $employee->id,
            'name' => 'Spouse Name',
            'relationship' => 'Spouse',
            'phone' => '+233244111222',
        ]);

        expect($employee->nextOfKin)->not->toBeNull()
            ->and($employee->nextOfKin->relationship)->toBe('Spouse');
    });

    test('employee has one bank details', function (): void {
        $employee = Employee::create(['first_name' => 'Test', 'last_name' => 'Employee']);

        BankDetails::create([
            'employee_id' => $employee->id,
            'bank_name' => 'Ghana Commercial Bank',
            'account_number' => '1234567890',
        ]);

        expect($employee->bankDetails)->not->toBeNull()
            ->and($employee->bankDetails->bank_name)->toBe('Ghana Commercial Bank');
    });

    test('employee has many emergency contacts', function (): void {
        $employee = Employee::create(['first_name' => 'Test', 'last_name' => 'Employee']);

        EmergencyContact::create([
            'employee_id' => $employee->id,
            'name' => 'Primary Contact',
            'phone' => '+233244111222',
            'is_primary' => true,
        ]);

        EmergencyContact::create([
            'employee_id' => $employee->id,
            'name' => 'Secondary Contact',
            'phone' => '+233244333444',
            'is_primary' => false,
        ]);

        expect($employee->emergencyContacts)->toHaveCount(2);
    });

    test('employee has many educational backgrounds', function (): void {
        $employee = Employee::create(['first_name' => 'Test', 'last_name' => 'Employee']);

        EducationalBackground::create([
            'employee_id' => $employee->id,
            'institution_name' => 'University of Ghana',
            'qualification' => 'BSc Computer Science',
            'start_date' => '2010-09-01',
            'end_date' => '2014-06-30',
        ]);

        expect($employee->educationalBackgrounds)->toHaveCount(1);
    });

    test('employee has many professional qualifications', function (): void {
        $employee = Employee::create(['first_name' => 'Test', 'last_name' => 'Employee']);

        ProfessionalQualification::create([
            'employee_id' => $employee->id,
            'certification_name' => 'PMP',
            'issuing_body' => 'PMI',
            'date_obtained' => '2020-01-15',
        ]);

        expect($employee->professionalQualifications)->toHaveCount(1);
    });

    test('employee has many previous work experiences', function (): void {
        $employee = Employee::create(['first_name' => 'Test', 'last_name' => 'Employee']);

        PreviousWorkExperience::create([
            'employee_id' => $employee->id,
            'company_name' => 'Previous Company Ltd',
            'position' => 'Software Developer',
            'start_date' => '2015-01-01',
            'end_date' => '2020-12-31',
        ]);

        expect($employee->previousWorkExperiences)->toHaveCount(1);
    });
});

describe('Employee Job Relationships', function (): void {
    test('employee has current job', function (): void {
        $employee = Employee::create(['first_name' => 'Test', 'last_name' => 'Employee']);

        CurrentJob::create([
            'employee_id' => $employee->id,
            'job_title' => 'Senior Developer',
            'start_date' => '2021-01-01',
            'is_current' => true,
        ]);

        expect($employee->currentJob)->not->toBeNull()
            ->and($employee->currentJob->job_title)->toBe('Senior Developer');
    });

    test('employee has job history', function (): void {
        $employee = Employee::create(['first_name' => 'Test', 'last_name' => 'Employee']);

        CurrentJob::create([
            'employee_id' => $employee->id,
            'job_title' => 'Junior Developer',
            'start_date' => '2019-01-01',
            'end_date' => '2020-12-31',
            'is_current' => false,
        ]);

        CurrentJob::create([
            'employee_id' => $employee->id,
            'job_title' => 'Senior Developer',
            'start_date' => '2021-01-01',
            'is_current' => true,
        ]);

        expect($employee->jobHistory)->toHaveCount(2);
    });

    test('employee has employment status history', function (): void {
        $employee = Employee::create(['first_name' => 'Test', 'last_name' => 'Employee']);

        EmploymentStatus::create([
            'employee_id' => $employee->id,
            'status' => EmploymentStatus::STATUS_ACTIVE,
            'effective_date' => '2021-01-01',
            'is_current' => true,
        ]);

        expect($employee->currentEmploymentStatus)->not->toBeNull()
            ->and($employee->currentEmploymentStatus->status)->toBe('active');
    });
});

describe('CurrentJob Model', function (): void {
    test('current job has supervisor relationship', function (): void {
        $supervisor = Employee::create(['first_name' => 'Manager', 'last_name' => 'Person']);
        $employee = Employee::create(['first_name' => 'Staff', 'last_name' => 'Member']);

        $job = CurrentJob::create([
            'employee_id' => $employee->id,
            'job_title' => 'Developer',
            'supervisor_id' => $supervisor->id,
            'is_current' => true,
        ]);

        expect($job->supervisor->displayName())->toBe('Manager Person');
    });
});

describe('ProfessionalQualification Model', function (): void {
    test('isExpired returns true for past expiry date', function (): void {
        $employee = Employee::create(['first_name' => 'Test', 'last_name' => 'Employee']);

        $qualification = ProfessionalQualification::create([
            'employee_id' => $employee->id,
            'certification_name' => 'Expired Cert',
            'issuing_body' => 'Test Body',
            'expiry_date' => now()->subDay(),
        ]);

        expect($qualification->isExpired())->toBeTrue();
    });

    test('isExpired returns false for future expiry date', function (): void {
        $employee = Employee::create(['first_name' => 'Test', 'last_name' => 'Employee']);

        $qualification = ProfessionalQualification::create([
            'employee_id' => $employee->id,
            'certification_name' => 'Valid Cert',
            'issuing_body' => 'Test Body',
            'expiry_date' => now()->addYear(),
        ]);

        expect($qualification->isExpired())->toBeFalse();
    });

    test('isExpired returns false when no expiry date', function (): void {
        $employee = Employee::create(['first_name' => 'Test', 'last_name' => 'Employee']);

        $qualification = ProfessionalQualification::create([
            'employee_id' => $employee->id,
            'certification_name' => 'Lifetime Cert',
            'issuing_body' => 'Test Body',
        ]);

        expect($qualification->isExpired())->toBeFalse();
    });

    test('active scope filters expired qualifications', function (): void {
        $employee = Employee::create(['first_name' => 'Test', 'last_name' => 'Employee']);

        ProfessionalQualification::create([
            'employee_id' => $employee->id,
            'certification_name' => 'Expired',
            'issuing_body' => 'Test',
            'expiry_date' => now()->subDay(),
        ]);

        ProfessionalQualification::create([
            'employee_id' => $employee->id,
            'certification_name' => 'Valid',
            'issuing_body' => 'Test',
            'expiry_date' => now()->addYear(),
        ]);

        ProfessionalQualification::create([
            'employee_id' => $employee->id,
            'certification_name' => 'No Expiry',
            'issuing_body' => 'Test',
        ]);

        expect(ProfessionalQualification::active()->count())->toBe(2);
    });
});

describe('EmergencyContact Model', function (): void {
    test('primary scope filters correctly', function (): void {
        $employee = Employee::create(['first_name' => 'Test', 'last_name' => 'Employee']);

        EmergencyContact::create([
            'employee_id' => $employee->id,
            'name' => 'Primary',
            'phone' => '1234',
            'is_primary' => true,
        ]);

        EmergencyContact::create([
            'employee_id' => $employee->id,
            'name' => 'Secondary',
            'phone' => '5678',
            'is_primary' => false,
        ]);

        expect(EmergencyContact::primary()->count())->toBe(1);
        expect(EmergencyContact::primary()->first()->name)->toBe('Primary');
    });
});

describe('EmploymentStatus Model', function (): void {
    test('status constants are defined', function (): void {
        expect(EmploymentStatus::STATUS_ACTIVE)->toBe('active')
            ->and(EmploymentStatus::STATUS_ON_LEAVE)->toBe('on_leave')
            ->and(EmploymentStatus::STATUS_SUSPENDED)->toBe('suspended')
            ->and(EmploymentStatus::STATUS_TERMINATED)->toBe('terminated')
            ->and(EmploymentStatus::STATUS_RESIGNED)->toBe('resigned')
            ->and(EmploymentStatus::STATUS_RETIRED)->toBe('retired');
    });

    test('statusOptions returns all options', function (): void {
        $options = EmploymentStatus::statusOptions();

        expect($options)->toHaveCount(6)
            ->and($options['active'])->toBe('Active')
            ->and($options['on_leave'])->toBe('On Leave');
    });

    test('current scope filters correctly', function (): void {
        $employee = Employee::create(['first_name' => 'Test', 'last_name' => 'Employee']);

        EmploymentStatus::create([
            'employee_id' => $employee->id,
            'status' => EmploymentStatus::STATUS_ACTIVE,
            'effective_date' => '2020-01-01',
            'end_date' => '2021-01-01',
            'is_current' => false,
        ]);

        EmploymentStatus::create([
            'employee_id' => $employee->id,
            'status' => EmploymentStatus::STATUS_ON_LEAVE,
            'effective_date' => '2021-01-01',
            'is_current' => true,
        ]);

        expect(EmploymentStatus::current()->count())->toBe(1);
        expect(EmploymentStatus::current()->first()->status)->toBe('on_leave');
    });
});
