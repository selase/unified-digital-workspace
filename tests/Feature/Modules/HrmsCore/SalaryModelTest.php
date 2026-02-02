<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Models\Organization\Grade;
use App\Modules\HrmsCore\Models\Salary\Allowance;
use App\Modules\HrmsCore\Models\Salary\AllowanceType;
use App\Modules\HrmsCore\Models\Salary\EmployeeAllowance;
use App\Modules\HrmsCore\Models\Salary\SalaryLevel;
use App\Modules\HrmsCore\Models\Salary\SalaryStep;
use App\Services\Tenancy\TenantContext;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create([
        'name' => 'Salary Test Company',
        'slug' => 'salary-test-company',
    ]);

    app(TenantContext::class)->setTenant($this->tenant);
});

describe('SalaryLevel Model', function (): void {
    test('can create a salary level', function (): void {
        $salaryLevel = SalaryLevel::create([
            'name' => 'Level 10',
            'base_salary' => 5000.00,
            'description' => 'Entry level salary',
        ]);

        expect($salaryLevel->id)->not->toBeNull()
            ->and($salaryLevel->uuid)->not->toBeNull()
            ->and($salaryLevel->tenant_id)->toBe($this->tenant->id)
            ->and($salaryLevel->name)->toBe('Level 10')
            ->and($salaryLevel->slug)->toBe('level-10')
            ->and((float) $salaryLevel->base_salary)->toBe(5000.00)
            ->and($salaryLevel->is_active)->toBeTrue();
    });

    test('salary level generates slug automatically', function (): void {
        $salaryLevel = SalaryLevel::create([
            'name' => 'Senior Manager Grade',
            'base_salary' => 15000.00,
        ]);

        expect($salaryLevel->slug)->toBe('senior-manager-grade');
    });

    test('salary level has relationship with grade', function (): void {
        $grade = Grade::create([
            'name' => 'Grade 5',
            'description' => 'Senior grade',
        ]);

        $salaryLevel = SalaryLevel::create([
            'name' => 'Level 5',
            'base_salary' => 8000.00,
            'grade_id' => $grade->id,
        ]);

        expect($salaryLevel->grade->id)->toBe($grade->id)
            ->and($salaryLevel->grade->name)->toBe('Grade 5');
    });

    test('salary level has many salary steps', function (): void {
        $salaryLevel = SalaryLevel::create([
            'name' => 'Level 3',
            'base_salary' => 6000.00,
        ]);

        SalaryStep::create([
            'name' => 'Step 1',
            'salary_level_id' => $salaryLevel->id,
            'step_number' => 1,
            'step_increment' => 0,
        ]);

        SalaryStep::create([
            'name' => 'Step 2',
            'salary_level_id' => $salaryLevel->id,
            'step_number' => 2,
            'step_increment' => 500.00,
        ]);

        expect($salaryLevel->salarySteps)->toHaveCount(2)
            ->and($salaryLevel->salarySteps->first()->name)->toBe('Step 1');
    });

    test('salary level has many employees', function (): void {
        $salaryLevel = SalaryLevel::create([
            'name' => 'Level 7',
            'base_salary' => 9000.00,
        ]);

        Employee::factory()->forTenant($this->tenant->id)->create([
            'salary_level_id' => $salaryLevel->id,
        ]);

        Employee::factory()->forTenant($this->tenant->id)->create([
            'salary_level_id' => $salaryLevel->id,
        ]);

        expect($salaryLevel->employees)->toHaveCount(2);
    });

    test('active scope filters correctly', function (): void {
        SalaryLevel::create(['name' => 'Active Level', 'base_salary' => 5000, 'is_active' => true]);
        SalaryLevel::create(['name' => 'Inactive Level', 'base_salary' => 5000, 'is_active' => false]);

        expect(SalaryLevel::active()->count())->toBe(1)
            ->and(SalaryLevel::count())->toBe(2);
    });

    test('ordered scope sorts by sort_order and name', function (): void {
        SalaryLevel::create(['name' => 'Zebra Level', 'base_salary' => 5000, 'sort_order' => 2]);
        SalaryLevel::create(['name' => 'Apple Level', 'base_salary' => 5000, 'sort_order' => 1]);
        SalaryLevel::create(['name' => 'Beta Level', 'base_salary' => 5000, 'sort_order' => 1]);

        $ordered = SalaryLevel::ordered()->get();

        expect($ordered[0]->name)->toBe('Apple Level')
            ->and($ordered[1]->name)->toBe('Beta Level')
            ->and($ordered[2]->name)->toBe('Zebra Level');
    });

    test('forGrade scope filters by grade', function (): void {
        $grade1 = Grade::create(['name' => 'Grade A']);
        $grade2 = Grade::create(['name' => 'Grade B']);

        SalaryLevel::create(['name' => 'Level A1', 'base_salary' => 5000, 'grade_id' => $grade1->id]);
        SalaryLevel::create(['name' => 'Level A2', 'base_salary' => 6000, 'grade_id' => $grade1->id]);
        SalaryLevel::create(['name' => 'Level B1', 'base_salary' => 7000, 'grade_id' => $grade2->id]);

        expect(SalaryLevel::forGrade($grade1->id)->count())->toBe(2)
            ->and(SalaryLevel::forGrade($grade2->id)->count())->toBe(1);
    });
});

describe('SalaryStep Model', function (): void {
    test('can create a salary step', function (): void {
        $salaryLevel = SalaryLevel::create([
            'name' => 'Level 1',
            'base_salary' => 5000.00,
        ]);

        $salaryStep = SalaryStep::create([
            'name' => 'Step 3',
            'salary_level_id' => $salaryLevel->id,
            'step_number' => 3,
            'step_increment' => 750.00,
        ]);

        expect($salaryStep->id)->not->toBeNull()
            ->and($salaryStep->uuid)->not->toBeNull()
            ->and($salaryStep->tenant_id)->toBe($this->tenant->id)
            ->and($salaryStep->name)->toBe('Step 3')
            ->and($salaryStep->slug)->toBe('step-3')
            ->and($salaryStep->step_number)->toBe(3)
            ->and((float) $salaryStep->step_increment)->toBe(750.00)
            ->and($salaryStep->is_active)->toBeTrue();
    });

    test('salary step generates slug automatically', function (): void {
        $salaryLevel = SalaryLevel::create(['name' => 'Level 1', 'base_salary' => 5000.00]);

        $salaryStep = SalaryStep::create([
            'name' => 'Senior Step Five',
            'salary_level_id' => $salaryLevel->id,
            'step_number' => 5,
            'step_increment' => 1000.00,
        ]);

        expect($salaryStep->slug)->toBe('senior-step-five');
    });

    test('salary step has relationship with salary level', function (): void {
        $salaryLevel = SalaryLevel::create([
            'name' => 'Level 2',
            'base_salary' => 6000.00,
        ]);

        $salaryStep = SalaryStep::create([
            'name' => 'Step 1',
            'salary_level_id' => $salaryLevel->id,
            'step_number' => 1,
            'step_increment' => 0,
        ]);

        expect($salaryStep->salaryLevel->id)->toBe($salaryLevel->id)
            ->and($salaryStep->salaryLevel->name)->toBe('Level 2');
    });

    test('salary step has relationship with grade', function (): void {
        $grade = Grade::create(['name' => 'Grade 3']);
        $salaryLevel = SalaryLevel::create(['name' => 'Level 3', 'base_salary' => 7000.00, 'grade_id' => $grade->id]);

        $salaryStep = SalaryStep::create([
            'name' => 'Step 1',
            'salary_level_id' => $salaryLevel->id,
            'grade_id' => $grade->id,
            'step_number' => 1,
            'step_increment' => 0,
        ]);

        expect($salaryStep->grade->id)->toBe($grade->id)
            ->and($salaryStep->grade->name)->toBe('Grade 3');
    });

    test('calculateTotalSalary returns base plus increment', function (): void {
        $salaryLevel = SalaryLevel::create([
            'name' => 'Level 4',
            'base_salary' => 8000.00,
        ]);

        $salaryStep = SalaryStep::create([
            'name' => 'Step 2',
            'salary_level_id' => $salaryLevel->id,
            'step_number' => 2,
            'step_increment' => 500.00,
        ]);

        // Reload to get the relationship
        $salaryStep = SalaryStep::with('salaryLevel')->find($salaryStep->id);

        expect($salaryStep->calculateTotalSalary())->toBe(8500.00);
    });

    test('calculateTotalSalary handles missing salary level', function (): void {
        $salaryStep = SalaryStep::create([
            'name' => 'Orphan Step',
            'step_number' => 1,
            'step_increment' => 500.00,
        ]);

        expect($salaryStep->calculateTotalSalary())->toBe(500.00);
    });

    test('active scope filters correctly', function (): void {
        $level = SalaryLevel::create(['name' => 'Level 1', 'base_salary' => 5000]);

        SalaryStep::create(['name' => 'Active Step', 'salary_level_id' => $level->id, 'step_number' => 1, 'step_increment' => 0, 'is_active' => true]);
        SalaryStep::create(['name' => 'Inactive Step', 'salary_level_id' => $level->id, 'step_number' => 2, 'step_increment' => 100, 'is_active' => false]);

        expect(SalaryStep::active()->count())->toBe(1)
            ->and(SalaryStep::count())->toBe(2);
    });

    test('ordered scope sorts by step number', function (): void {
        $level = SalaryLevel::create(['name' => 'Level 1', 'base_salary' => 5000]);

        SalaryStep::create(['name' => 'Step 3', 'salary_level_id' => $level->id, 'step_number' => 3, 'step_increment' => 200]);
        SalaryStep::create(['name' => 'Step 1', 'salary_level_id' => $level->id, 'step_number' => 1, 'step_increment' => 0]);
        SalaryStep::create(['name' => 'Step 2', 'salary_level_id' => $level->id, 'step_number' => 2, 'step_increment' => 100]);

        $ordered = SalaryStep::ordered()->get();

        expect($ordered[0]->step_number)->toBe(1)
            ->and($ordered[1]->step_number)->toBe(2)
            ->and($ordered[2]->step_number)->toBe(3);
    });

    test('forLevel scope filters by salary level', function (): void {
        $level1 = SalaryLevel::create(['name' => 'Level 1', 'base_salary' => 5000]);
        $level2 = SalaryLevel::create(['name' => 'Level 2', 'base_salary' => 6000]);

        SalaryStep::create(['name' => 'L1-S1', 'salary_level_id' => $level1->id, 'step_number' => 1, 'step_increment' => 0]);
        SalaryStep::create(['name' => 'L1-S2', 'salary_level_id' => $level1->id, 'step_number' => 2, 'step_increment' => 100]);
        SalaryStep::create(['name' => 'L2-S1', 'salary_level_id' => $level2->id, 'step_number' => 1, 'step_increment' => 0]);

        expect(SalaryStep::forLevel($level1->id)->count())->toBe(2)
            ->and(SalaryStep::forLevel($level2->id)->count())->toBe(1);
    });
});

describe('AllowanceType Model', function (): void {
    test('can create an allowance type', function (): void {
        $type = AllowanceType::create([
            'name' => 'Housing Allowance',
            'description' => 'Monthly housing support',
        ]);

        expect($type->id)->not->toBeNull()
            ->and($type->uuid)->not->toBeNull()
            ->and($type->tenant_id)->toBe($this->tenant->id)
            ->and($type->name)->toBe('Housing Allowance')
            ->and($type->slug)->toBe('housing-allowance')
            ->and($type->is_taxable)->toBeTrue()
            ->and($type->is_active)->toBeTrue();
    });

    test('allowance type generates slug automatically', function (): void {
        $type = AllowanceType::create([
            'name' => 'Transportation Benefit',
        ]);

        expect($type->slug)->toBe('transportation-benefit');
    });

    test('allowance type has many allowances', function (): void {
        $type = AllowanceType::create(['name' => 'Bonus']);

        Allowance::create([
            'name' => 'Performance Bonus',
            'allowance_type_id' => $type->id,
            'amount' => 1000.00,
        ]);

        Allowance::create([
            'name' => 'Year End Bonus',
            'allowance_type_id' => $type->id,
            'amount' => 2000.00,
        ]);

        expect($type->allowances)->toHaveCount(2);
    });

    test('active scope filters correctly', function (): void {
        AllowanceType::create(['name' => 'Active Type', 'is_active' => true]);
        AllowanceType::create(['name' => 'Inactive Type', 'is_active' => false]);

        expect(AllowanceType::active()->count())->toBe(1)
            ->and(AllowanceType::count())->toBe(2);
    });

    test('taxable scope filters correctly', function (): void {
        AllowanceType::create(['name' => 'Taxable Type', 'is_taxable' => true]);
        AllowanceType::create(['name' => 'Non-taxable Type', 'is_taxable' => false]);

        expect(AllowanceType::taxable()->count())->toBe(1);
    });

    test('ordered scope sorts by sort_order and name', function (): void {
        AllowanceType::create(['name' => 'Zebra Type', 'sort_order' => 2]);
        AllowanceType::create(['name' => 'Apple Type', 'sort_order' => 1]);
        AllowanceType::create(['name' => 'Beta Type', 'sort_order' => 1]);

        $ordered = AllowanceType::ordered()->get();

        expect($ordered[0]->name)->toBe('Apple Type')
            ->and($ordered[1]->name)->toBe('Beta Type')
            ->and($ordered[2]->name)->toBe('Zebra Type');
    });
});

describe('Allowance Model', function (): void {
    test('can create an allowance', function (): void {
        $type = AllowanceType::create(['name' => 'Housing']);

        $allowance = Allowance::create([
            'name' => 'Staff Housing',
            'allowance_type_id' => $type->id,
            'amount' => 1500.00,
            'frequency' => Allowance::FREQUENCY_MONTHLY,
        ]);

        expect($allowance->id)->not->toBeNull()
            ->and($allowance->uuid)->not->toBeNull()
            ->and($allowance->tenant_id)->toBe($this->tenant->id)
            ->and($allowance->name)->toBe('Staff Housing')
            ->and($allowance->slug)->toBe('staff-housing')
            ->and((float) $allowance->amount)->toBe(1500.00)
            ->and($allowance->frequency)->toBe('monthly')
            ->and($allowance->is_active)->toBeTrue();
    });

    test('allowance generates slug automatically', function (): void {
        $type = AllowanceType::create(['name' => 'Transport']);

        $allowance = Allowance::create([
            'name' => 'Vehicle Maintenance Allowance',
            'allowance_type_id' => $type->id,
            'amount' => 500.00,
        ]);

        expect($allowance->slug)->toBe('vehicle-maintenance-allowance');
    });

    test('allowance has relationship with allowance type', function (): void {
        $type = AllowanceType::create(['name' => 'Medical']);

        $allowance = Allowance::create([
            'name' => 'Health Insurance',
            'allowance_type_id' => $type->id,
            'amount' => 800.00,
        ]);

        expect($allowance->allowanceType->id)->toBe($type->id)
            ->and($allowance->allowanceType->name)->toBe('Medical');
    });

    test('allowance has relationship with grade', function (): void {
        $type = AllowanceType::create(['name' => 'Entertainment']);
        $grade = Grade::create(['name' => 'Grade 1']);

        $allowance = Allowance::create([
            'name' => 'Entertainment Allowance',
            'allowance_type_id' => $type->id,
            'grade_id' => $grade->id,
            'amount' => 300.00,
        ]);

        expect($allowance->grade->id)->toBe($grade->id)
            ->and($allowance->grade->name)->toBe('Grade 1');
    });

    test('isTaxable returns true when type is taxable', function (): void {
        $taxableType = AllowanceType::create(['name' => 'Taxable', 'is_taxable' => true]);
        $allowance = Allowance::create([
            'name' => 'Taxable Allowance',
            'allowance_type_id' => $taxableType->id,
            'amount' => 1000.00,
        ]);

        // Reload with relationship
        $allowance = Allowance::with('allowanceType')->find($allowance->id);

        expect($allowance->isTaxable())->toBeTrue();
    });

    test('isTaxable returns false when type is not taxable', function (): void {
        $nonTaxableType = AllowanceType::create(['name' => 'Non-Taxable', 'is_taxable' => false]);
        $allowance = Allowance::create([
            'name' => 'Non-Taxable Allowance',
            'allowance_type_id' => $nonTaxableType->id,
            'amount' => 1000.00,
        ]);

        // Reload with relationship
        $allowance = Allowance::with('allowanceType')->find($allowance->id);

        expect($allowance->isTaxable())->toBeFalse();
    });

    test('getMonthlyAmount returns amount for monthly frequency', function (): void {
        $type = AllowanceType::create(['name' => 'Housing']);
        $allowance = Allowance::create([
            'name' => 'Monthly Allowance',
            'allowance_type_id' => $type->id,
            'amount' => 1200.00,
            'frequency' => Allowance::FREQUENCY_MONTHLY,
        ]);

        expect($allowance->getMonthlyAmount())->toBe(1200.00);
    });

    test('getMonthlyAmount returns divided amount for annual frequency', function (): void {
        $type = AllowanceType::create(['name' => 'Bonus']);
        $allowance = Allowance::create([
            'name' => 'Annual Allowance',
            'allowance_type_id' => $type->id,
            'amount' => 12000.00,
            'frequency' => Allowance::FREQUENCY_ANNUAL,
        ]);

        expect($allowance->getMonthlyAmount())->toBe(1000.00);
    });

    test('getMonthlyAmount returns zero for one-time frequency', function (): void {
        $type = AllowanceType::create(['name' => 'Signing']);
        $allowance = Allowance::create([
            'name' => 'One-Time Allowance',
            'allowance_type_id' => $type->id,
            'amount' => 5000.00,
            'frequency' => Allowance::FREQUENCY_ONE_TIME,
        ]);

        expect($allowance->getMonthlyAmount())->toBe(0.0);
    });

    test('frequencyOptions returns available options', function (): void {
        $options = Allowance::frequencyOptions();

        expect($options)->toHaveCount(3)
            ->and($options)->toHaveKey('monthly')
            ->and($options)->toHaveKey('annual')
            ->and($options)->toHaveKey('one-time');
    });

    test('active scope filters correctly', function (): void {
        $type = AllowanceType::create(['name' => 'Test']);

        Allowance::create(['name' => 'Active', 'allowance_type_id' => $type->id, 'amount' => 100, 'is_active' => true]);
        Allowance::create(['name' => 'Inactive', 'allowance_type_id' => $type->id, 'amount' => 100, 'is_active' => false]);

        expect(Allowance::active()->count())->toBe(1)
            ->and(Allowance::count())->toBe(2);
    });

    test('ofType scope filters by allowance type', function (): void {
        $type1 = AllowanceType::create(['name' => 'Type 1']);
        $type2 = AllowanceType::create(['name' => 'Type 2']);

        Allowance::create(['name' => 'A1', 'allowance_type_id' => $type1->id, 'amount' => 100]);
        Allowance::create(['name' => 'A2', 'allowance_type_id' => $type1->id, 'amount' => 200]);
        Allowance::create(['name' => 'B1', 'allowance_type_id' => $type2->id, 'amount' => 300]);

        expect(Allowance::ofType($type1->id)->count())->toBe(2)
            ->and(Allowance::ofType($type2->id)->count())->toBe(1);
    });

    test('withFrequency scope filters by frequency', function (): void {
        $type = AllowanceType::create(['name' => 'Test']);

        Allowance::create(['name' => 'Monthly A', 'allowance_type_id' => $type->id, 'amount' => 100, 'frequency' => 'monthly']);
        Allowance::create(['name' => 'Monthly B', 'allowance_type_id' => $type->id, 'amount' => 200, 'frequency' => 'monthly']);
        Allowance::create(['name' => 'Annual', 'allowance_type_id' => $type->id, 'amount' => 1200, 'frequency' => 'annual']);

        expect(Allowance::withFrequency('monthly')->count())->toBe(2)
            ->and(Allowance::withFrequency('annual')->count())->toBe(1);
    });
});

describe('EmployeeAllowance Model', function (): void {
    test('can create an employee allowance', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $type = AllowanceType::create(['name' => 'Housing']);
        $allowance = Allowance::create([
            'name' => 'Housing Allowance',
            'allowance_type_id' => $type->id,
            'amount' => 1500.00,
        ]);

        $employeeAllowance = EmployeeAllowance::create([
            'employee_id' => $employee->id,
            'allowance_id' => $allowance->id,
            'effective_from' => now()->subMonth(),
        ]);

        expect($employeeAllowance->id)->not->toBeNull()
            ->and($employeeAllowance->uuid)->not->toBeNull()
            ->and($employeeAllowance->tenant_id)->toBe($this->tenant->id)
            ->and($employeeAllowance->employee_id)->toBe($employee->id)
            ->and($employeeAllowance->allowance_id)->toBe($allowance->id)
            ->and($employeeAllowance->is_active)->toBeTrue();
    });

    test('employee allowance has relationship with employee', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $type = AllowanceType::create(['name' => 'Transport']);
        $allowance = Allowance::create(['name' => 'Transport Allowance', 'allowance_type_id' => $type->id, 'amount' => 500]);

        $employeeAllowance = EmployeeAllowance::create([
            'employee_id' => $employee->id,
            'allowance_id' => $allowance->id,
            'effective_from' => now(),
        ]);

        expect($employeeAllowance->employee->id)->toBe($employee->id);
    });

    test('employee allowance has relationship with allowance', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $type = AllowanceType::create(['name' => 'Medical']);
        $allowance = Allowance::create(['name' => 'Medical Allowance', 'allowance_type_id' => $type->id, 'amount' => 800]);

        $employeeAllowance = EmployeeAllowance::create([
            'employee_id' => $employee->id,
            'allowance_id' => $allowance->id,
            'effective_from' => now(),
        ]);

        expect($employeeAllowance->allowance->id)->toBe($allowance->id)
            ->and($employeeAllowance->allowance->name)->toBe('Medical Allowance');
    });

    test('getEffectiveAmount returns override when set', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $type = AllowanceType::create(['name' => 'Bonus']);
        $allowance = Allowance::create(['name' => 'Bonus', 'allowance_type_id' => $type->id, 'amount' => 1000.00]);

        $employeeAllowance = EmployeeAllowance::create([
            'employee_id' => $employee->id,
            'allowance_id' => $allowance->id,
            'amount' => 1500.00, // Override amount
            'effective_from' => now(),
        ]);

        expect($employeeAllowance->getEffectiveAmount())->toBe(1500.00);
    });

    test('getEffectiveAmount returns allowance amount when no override', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $type = AllowanceType::create(['name' => 'Bonus']);
        $allowance = Allowance::create(['name' => 'Bonus', 'allowance_type_id' => $type->id, 'amount' => 1000.00]);

        $employeeAllowance = EmployeeAllowance::create([
            'employee_id' => $employee->id,
            'allowance_id' => $allowance->id,
            'effective_from' => now(),
        ]);

        // Reload with relationship
        $employeeAllowance = EmployeeAllowance::with('allowance')->find($employeeAllowance->id);

        expect($employeeAllowance->getEffectiveAmount())->toBe(1000.00);
    });

    test('isCurrentlyEffective returns true for active current allowances', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $type = AllowanceType::create(['name' => 'Housing']);
        $allowance = Allowance::create(['name' => 'Housing', 'allowance_type_id' => $type->id, 'amount' => 1000]);

        $employeeAllowance = EmployeeAllowance::create([
            'employee_id' => $employee->id,
            'allowance_id' => $allowance->id,
            'effective_from' => now()->subMonth(),
            'effective_to' => now()->addMonth(),
            'is_active' => true,
        ]);

        expect($employeeAllowance->isCurrentlyEffective())->toBeTrue();
    });

    test('isCurrentlyEffective returns false for inactive allowances', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $type = AllowanceType::create(['name' => 'Housing']);
        $allowance = Allowance::create(['name' => 'Housing', 'allowance_type_id' => $type->id, 'amount' => 1000]);

        $employeeAllowance = EmployeeAllowance::create([
            'employee_id' => $employee->id,
            'allowance_id' => $allowance->id,
            'effective_from' => now()->subMonth(),
            'is_active' => false,
        ]);

        expect($employeeAllowance->isCurrentlyEffective())->toBeFalse();
    });

    test('isCurrentlyEffective returns false for future allowances', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $type = AllowanceType::create(['name' => 'Housing']);
        $allowance = Allowance::create(['name' => 'Housing', 'allowance_type_id' => $type->id, 'amount' => 1000]);

        $employeeAllowance = EmployeeAllowance::create([
            'employee_id' => $employee->id,
            'allowance_id' => $allowance->id,
            'effective_from' => now()->addMonth(),
            'is_active' => true,
        ]);

        expect($employeeAllowance->isCurrentlyEffective())->toBeFalse();
    });

    test('isCurrentlyEffective returns false for expired allowances', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $type = AllowanceType::create(['name' => 'Housing']);
        $allowance = Allowance::create(['name' => 'Housing', 'allowance_type_id' => $type->id, 'amount' => 1000]);

        $employeeAllowance = EmployeeAllowance::create([
            'employee_id' => $employee->id,
            'allowance_id' => $allowance->id,
            'effective_from' => now()->subYear(),
            'effective_to' => now()->subMonth(),
            'is_active' => true,
        ]);

        expect($employeeAllowance->isCurrentlyEffective())->toBeFalse();
    });

    test('isTaxable returns value from allowance type', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $taxableType = AllowanceType::create(['name' => 'Taxable', 'is_taxable' => true]);
        $allowance = Allowance::create(['name' => 'Taxable Allowance', 'allowance_type_id' => $taxableType->id, 'amount' => 1000]);

        $employeeAllowance = EmployeeAllowance::create([
            'employee_id' => $employee->id,
            'allowance_id' => $allowance->id,
            'effective_from' => now(),
        ]);

        // Reload with relationships
        $employeeAllowance = EmployeeAllowance::with('allowance.allowanceType')->find($employeeAllowance->id);

        expect($employeeAllowance->isTaxable())->toBeTrue();
    });

    test('active scope filters correctly', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $type = AllowanceType::create(['name' => 'Test']);
        $allowance = Allowance::create(['name' => 'Test', 'allowance_type_id' => $type->id, 'amount' => 100]);

        EmployeeAllowance::create(['employee_id' => $employee->id, 'allowance_id' => $allowance->id, 'effective_from' => now(), 'is_active' => true]);
        EmployeeAllowance::create(['employee_id' => $employee->id, 'allowance_id' => $allowance->id, 'effective_from' => now()->subYear(), 'is_active' => false]);

        expect(EmployeeAllowance::active()->count())->toBe(1)
            ->and(EmployeeAllowance::count())->toBe(2);
    });

    test('currentlyEffective scope filters correctly', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $type = AllowanceType::create(['name' => 'Test']);
        $allowance = Allowance::create(['name' => 'Test', 'allowance_type_id' => $type->id, 'amount' => 100]);

        // Current
        EmployeeAllowance::create([
            'employee_id' => $employee->id,
            'allowance_id' => $allowance->id,
            'effective_from' => now()->subMonth(),
            'is_active' => true,
        ]);

        // Future
        EmployeeAllowance::create([
            'employee_id' => $employee->id,
            'allowance_id' => $allowance->id,
            'effective_from' => now()->addMonth(),
            'is_active' => true,
        ]);

        // Expired
        EmployeeAllowance::create([
            'employee_id' => $employee->id,
            'allowance_id' => $allowance->id,
            'effective_from' => now()->subYear(),
            'effective_to' => now()->subMonth(),
            'is_active' => true,
        ]);

        expect(EmployeeAllowance::currentlyEffective()->count())->toBe(1)
            ->and(EmployeeAllowance::count())->toBe(3);
    });

    test('forEmployee scope filters by employee', function (): void {
        $employee1 = Employee::factory()->forTenant($this->tenant->id)->create();
        $employee2 = Employee::factory()->forTenant($this->tenant->id)->create();
        $type = AllowanceType::create(['name' => 'Test']);
        $allowance = Allowance::create(['name' => 'Test', 'allowance_type_id' => $type->id, 'amount' => 100]);

        EmployeeAllowance::create(['employee_id' => $employee1->id, 'allowance_id' => $allowance->id, 'effective_from' => now()]);
        EmployeeAllowance::create(['employee_id' => $employee1->id, 'allowance_id' => $allowance->id, 'effective_from' => now()->subMonth()]);
        EmployeeAllowance::create(['employee_id' => $employee2->id, 'allowance_id' => $allowance->id, 'effective_from' => now()]);

        expect(EmployeeAllowance::forEmployee($employee1->id)->count())->toBe(2)
            ->and(EmployeeAllowance::forEmployee($employee2->id)->count())->toBe(1);
    });

    test('forAllowance scope filters by allowance', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $type = AllowanceType::create(['name' => 'Test']);
        $allowance1 = Allowance::create(['name' => 'A1', 'allowance_type_id' => $type->id, 'amount' => 100]);
        $allowance2 = Allowance::create(['name' => 'A2', 'allowance_type_id' => $type->id, 'amount' => 200]);

        EmployeeAllowance::create(['employee_id' => $employee->id, 'allowance_id' => $allowance1->id, 'effective_from' => now()]);
        EmployeeAllowance::create(['employee_id' => $employee->id, 'allowance_id' => $allowance1->id, 'effective_from' => now()->subMonth()]);
        EmployeeAllowance::create(['employee_id' => $employee->id, 'allowance_id' => $allowance2->id, 'effective_from' => now()]);

        expect(EmployeeAllowance::forAllowance($allowance1->id)->count())->toBe(2)
            ->and(EmployeeAllowance::forAllowance($allowance2->id)->count())->toBe(1);
    });
});

describe('Employee Salary Relationships', function (): void {
    test('employee has salary level relationship', function (): void {
        $salaryLevel = SalaryLevel::create(['name' => 'Level 5', 'base_salary' => 8000.00]);

        $employee = Employee::factory()->forTenant($this->tenant->id)->create([
            'salary_level_id' => $salaryLevel->id,
        ]);

        expect($employee->salaryLevel->id)->toBe($salaryLevel->id)
            ->and($employee->salaryLevel->name)->toBe('Level 5');
    });

    test('employee has salary step relationship', function (): void {
        $salaryLevel = SalaryLevel::create(['name' => 'Level 5', 'base_salary' => 8000.00]);
        $salaryStep = SalaryStep::create([
            'name' => 'Step 3',
            'salary_level_id' => $salaryLevel->id,
            'step_number' => 3,
            'step_increment' => 500.00,
        ]);

        $employee = Employee::factory()->forTenant($this->tenant->id)->create([
            'salary_level_id' => $salaryLevel->id,
            'salary_step_id' => $salaryStep->id,
        ]);

        expect($employee->salaryStep->id)->toBe($salaryStep->id)
            ->and($employee->salaryStep->name)->toBe('Step 3');
    });

    test('employee has employee allowances relationship', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $type = AllowanceType::create(['name' => 'Housing']);
        $allowance1 = Allowance::create(['name' => 'Housing', 'allowance_type_id' => $type->id, 'amount' => 1500]);
        $allowance2 = Allowance::create(['name' => 'Transport', 'allowance_type_id' => $type->id, 'amount' => 500]);

        EmployeeAllowance::create(['employee_id' => $employee->id, 'allowance_id' => $allowance1->id, 'effective_from' => now()]);
        EmployeeAllowance::create(['employee_id' => $employee->id, 'allowance_id' => $allowance2->id, 'effective_from' => now()]);

        expect($employee->employeeAllowances)->toHaveCount(2);
    });

    test('employee active allowances returns only currently effective', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $type = AllowanceType::create(['name' => 'Test']);
        $allowance = Allowance::create(['name' => 'Test', 'allowance_type_id' => $type->id, 'amount' => 1000]);

        // Current
        EmployeeAllowance::create([
            'employee_id' => $employee->id,
            'allowance_id' => $allowance->id,
            'effective_from' => now()->subMonth(),
            'is_active' => true,
        ]);

        // Future
        EmployeeAllowance::create([
            'employee_id' => $employee->id,
            'allowance_id' => $allowance->id,
            'effective_from' => now()->addMonth(),
            'is_active' => true,
        ]);

        expect($employee->activeAllowances)->toHaveCount(1);
    });

    test('getBaseSalary returns calculated salary from step', function (): void {
        $salaryLevel = SalaryLevel::create(['name' => 'Level 5', 'base_salary' => 8000.00]);
        $salaryStep = SalaryStep::create([
            'name' => 'Step 3',
            'salary_level_id' => $salaryLevel->id,
            'step_number' => 3,
            'step_increment' => 500.00,
        ]);

        $employee = Employee::factory()->forTenant($this->tenant->id)->create([
            'salary_level_id' => $salaryLevel->id,
            'salary_step_id' => $salaryStep->id,
        ]);

        // Reload to ensure relationships are fresh
        $employee = Employee::with('salaryStep.salaryLevel')->find($employee->id);

        expect($employee->getBaseSalary())->toBe(8500.00);
    });

    test('getBaseSalary returns zero when no salary step', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create([
            'salary_level_id' => null,
            'salary_step_id' => null,
        ]);

        expect($employee->getBaseSalary())->toBe(0.0);
    });

    test('getTotalMonthlyAllowances calculates sum of active allowances', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $type = AllowanceType::create(['name' => 'General']);
        $allowance1 = Allowance::create(['name' => 'Housing', 'allowance_type_id' => $type->id, 'amount' => 1500.00]);
        $allowance2 = Allowance::create(['name' => 'Transport', 'allowance_type_id' => $type->id, 'amount' => 500.00]);

        EmployeeAllowance::create([
            'employee_id' => $employee->id,
            'allowance_id' => $allowance1->id,
            'effective_from' => now()->subMonth(),
            'is_active' => true,
        ]);

        EmployeeAllowance::create([
            'employee_id' => $employee->id,
            'allowance_id' => $allowance2->id,
            'effective_from' => now()->subMonth(),
            'is_active' => true,
        ]);

        expect($employee->getTotalMonthlyAllowances())->toBe(2000.00);
    });

    test('getTotalCompensation returns salary plus allowances', function (): void {
        $salaryLevel = SalaryLevel::create(['name' => 'Level 5', 'base_salary' => 8000.00]);
        $salaryStep = SalaryStep::create([
            'name' => 'Step 2',
            'salary_level_id' => $salaryLevel->id,
            'step_number' => 2,
            'step_increment' => 300.00,
        ]);

        $employee = Employee::factory()->forTenant($this->tenant->id)->create([
            'salary_level_id' => $salaryLevel->id,
            'salary_step_id' => $salaryStep->id,
        ]);

        $type = AllowanceType::create(['name' => 'General']);
        $allowance = Allowance::create(['name' => 'Housing', 'allowance_type_id' => $type->id, 'amount' => 1000.00]);

        EmployeeAllowance::create([
            'employee_id' => $employee->id,
            'allowance_id' => $allowance->id,
            'effective_from' => now()->subMonth(),
            'is_active' => true,
        ]);

        // Reload employee with relationships
        $employee = Employee::with('salaryStep.salaryLevel')->find($employee->id);

        // Base: 8000 + Step: 300 + Allowance: 1000 = 9300
        expect($employee->getTotalCompensation())->toBe(9300.00);
    });
});
