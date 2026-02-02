<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Modules\HrmsCore\Enums\LeaveStatus;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Models\Leave\Holiday;
use App\Modules\HrmsCore\Models\Leave\LeaveBalance;
use App\Modules\HrmsCore\Models\Leave\LeaveCategory;
use App\Modules\HrmsCore\Models\Leave\LeaveRequest;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create([
        'name' => 'Leave Test Company',
        'slug' => 'leave-test-company',
    ]);

    app(TenantContext::class)->setTenant($this->tenant);
});

describe('LeaveCategory Model', function (): void {
    test('can create a leave category', function (): void {
        $category = LeaveCategory::create([
            'name' => 'Annual Leave',
            'default_days' => 21,
            'description' => 'Standard annual leave entitlement',
        ]);

        expect($category->id)->not->toBeNull()
            ->and($category->uuid)->not->toBeNull()
            ->and($category->tenant_id)->toBe($this->tenant->id)
            ->and($category->name)->toBe('Annual Leave')
            ->and($category->slug)->toBe('annual-leave')
            ->and($category->default_days)->toBe(21)
            ->and($category->is_paid)->toBeTrue()
            ->and($category->is_active)->toBeTrue();
    });

    test('leave category generates slug automatically', function (): void {
        $category = LeaveCategory::create([
            'name' => 'Maternity Leave',
            'default_days' => 90,
        ]);

        expect($category->slug)->toBe('maternity-leave');
    });

    test('active scope filters correctly', function (): void {
        LeaveCategory::create(['name' => 'Active Category', 'default_days' => 10, 'is_active' => true]);
        LeaveCategory::create(['name' => 'Inactive Category', 'default_days' => 10, 'is_active' => false]);

        expect(LeaveCategory::active()->count())->toBe(1);
        expect(LeaveCategory::count())->toBe(2);
    });

    test('ordered scope sorts by sort_order and name', function (): void {
        LeaveCategory::create(['name' => 'Zebra Leave', 'default_days' => 10, 'sort_order' => 2]);
        LeaveCategory::create(['name' => 'Apple Leave', 'default_days' => 10, 'sort_order' => 1]);
        LeaveCategory::create(['name' => 'Beta Leave', 'default_days' => 10, 'sort_order' => 1]);

        $ordered = LeaveCategory::ordered()->get();

        expect($ordered[0]->name)->toBe('Apple Leave')
            ->and($ordered[1]->name)->toBe('Beta Leave')
            ->and($ordered[2]->name)->toBe('Zebra Leave');
    });

    test('leave category factory creates valid model', function (): void {
        $category = LeaveCategory::factory()->forTenant($this->tenant->id)->create();

        expect($category->id)->not->toBeNull()
            ->and($category->name)->not->toBeNull()
            ->and($category->default_days)->toBeGreaterThan(0);
    });
});

describe('Holiday Model', function (): void {
    test('can create a holiday', function (): void {
        $holiday = Holiday::create([
            'name' => 'Independence Day',
            'date' => '2024-03-06',
            'description' => 'Ghana Independence Day',
        ]);

        expect($holiday->id)->not->toBeNull()
            ->and($holiday->uuid)->not->toBeNull()
            ->and($holiday->name)->toBe('Independence Day')
            ->and($holiday->date->format('Y-m-d'))->toBe('2024-03-06')
            ->and($holiday->is_recurring)->toBeFalse()
            ->and($holiday->is_active)->toBeTrue();
    });

    test('can create a recurring holiday', function (): void {
        $holiday = Holiday::create([
            'name' => 'New Year',
            'date' => '2024-01-01',
            'is_recurring' => true,
        ]);

        expect($holiday->is_recurring)->toBeTrue();
    });

    test('isOnDate works for non-recurring holidays', function (): void {
        $holiday = Holiday::create([
            'name' => 'Special Day',
            'date' => '2024-07-01',
            'is_recurring' => false,
        ]);

        expect($holiday->isOnDate(Carbon::parse('2024-07-01')))->toBeTrue()
            ->and($holiday->isOnDate(Carbon::parse('2025-07-01')))->toBeFalse();
    });

    test('isOnDate works for recurring holidays', function (): void {
        $holiday = Holiday::create([
            'name' => 'Annual Celebration',
            'date' => '2024-07-01',
            'is_recurring' => true,
        ]);

        expect($holiday->isOnDate(Carbon::parse('2024-07-01')))->toBeTrue()
            ->and($holiday->isOnDate(Carbon::parse('2025-07-01')))->toBeTrue()
            ->and($holiday->isOnDate(Carbon::parse('2024-08-01')))->toBeFalse();
    });

    test('countBetweenDates returns correct count', function (): void {
        Holiday::create(['name' => 'Holiday 1', 'date' => '2024-07-01']);
        Holiday::create(['name' => 'Holiday 2', 'date' => '2024-07-04']);
        Holiday::create(['name' => 'Holiday 3', 'date' => '2024-07-10']);

        $count = Holiday::countBetweenDates(
            Carbon::parse('2024-07-01'),
            Carbon::parse('2024-07-05')
        );

        expect($count)->toBe(2);
    });

    test('active scope filters correctly', function (): void {
        Holiday::create(['name' => 'Active Holiday', 'date' => '2024-01-01', 'is_active' => true]);
        Holiday::create(['name' => 'Inactive Holiday', 'date' => '2024-01-02', 'is_active' => false]);

        expect(Holiday::active()->count())->toBe(1);
    });
});

describe('LeaveBalance Model', function (): void {
    test('can create a leave balance', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $category = LeaveCategory::create(['name' => 'Annual Leave', 'default_days' => 21]);

        $balance = LeaveBalance::create([
            'employee_id' => $employee->id,
            'leave_category_id' => $category->id,
            'year' => 2024,
            'entitled_days' => 21,
            'carried_forward_days' => 5,
        ]);

        expect($balance->id)->not->toBeNull()
            ->and($balance->uuid)->not->toBeNull()
            ->and($balance->entitled_days)->toBe(21)
            ->and($balance->carried_forward_days)->toBe(5)
            ->and($balance->used_days)->toBe(0)
            ->and($balance->pending_days)->toBe(0);
    });

    test('remaining_days is calculated correctly', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $category = LeaveCategory::create(['name' => 'Annual Leave', 'default_days' => 21]);

        $balance = LeaveBalance::create([
            'employee_id' => $employee->id,
            'leave_category_id' => $category->id,
            'year' => 2024,
            'entitled_days' => 21,
            'carried_forward_days' => 5,
            'used_days' => 10,
            'pending_days' => 3,
        ]);

        // Refresh to get the computed column
        $balance->refresh();

        // remaining = entitled(21) + carried(5) - used(10) - pending(3) = 13
        expect($balance->remaining_days)->toBe(13);
    });

    test('getTotalAvailable returns entitled plus carry forward', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $category = LeaveCategory::create(['name' => 'Annual Leave', 'default_days' => 21]);

        $balance = LeaveBalance::create([
            'employee_id' => $employee->id,
            'leave_category_id' => $category->id,
            'year' => 2024,
            'entitled_days' => 21,
            'carried_forward_days' => 5,
        ]);

        expect($balance->getTotalAvailable())->toBe(26);
    });

    test('hasEnoughDays checks remaining balance', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $category = LeaveCategory::create(['name' => 'Annual Leave', 'default_days' => 21]);

        $balance = LeaveBalance::create([
            'employee_id' => $employee->id,
            'leave_category_id' => $category->id,
            'year' => 2024,
            'entitled_days' => 21,
            'used_days' => 15,
        ]);

        $balance->refresh();

        expect($balance->hasEnoughDays(5))->toBeTrue()
            ->and($balance->hasEnoughDays(10))->toBeFalse();
    });

    test('addPendingDays increases pending count', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $category = LeaveCategory::create(['name' => 'Annual Leave', 'default_days' => 21]);

        $balance = LeaveBalance::create([
            'employee_id' => $employee->id,
            'leave_category_id' => $category->id,
            'year' => 2024,
            'entitled_days' => 21,
        ]);

        $balance->addPendingDays(5);

        expect($balance->pending_days)->toBe(5);
    });

    test('convertPendingToUsed moves days correctly', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $category = LeaveCategory::create(['name' => 'Annual Leave', 'default_days' => 21]);

        $balance = LeaveBalance::create([
            'employee_id' => $employee->id,
            'leave_category_id' => $category->id,
            'year' => 2024,
            'entitled_days' => 21,
            'pending_days' => 10,
        ]);

        $balance->convertPendingToUsed(10);

        expect($balance->pending_days)->toBe(0)
            ->and($balance->used_days)->toBe(10);
    });

    test('getOrCreateForEmployee creates balance if not exists', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $category = LeaveCategory::create(['name' => 'Annual Leave', 'default_days' => 21]);

        $balance = LeaveBalance::getOrCreateForEmployee($employee->id, $category->id, 2024);

        expect($balance->id)->not->toBeNull()
            ->and($balance->entitled_days)->toBe(21)
            ->and($balance->year)->toBe(2024);
    });

    test('getOrCreateForEmployee returns existing balance', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $category = LeaveCategory::create(['name' => 'Annual Leave', 'default_days' => 21]);

        $existingBalance = LeaveBalance::create([
            'employee_id' => $employee->id,
            'leave_category_id' => $category->id,
            'year' => 2024,
            'entitled_days' => 30, // Different from default
        ]);

        $balance = LeaveBalance::getOrCreateForEmployee($employee->id, $category->id, 2024);

        expect($balance->id)->toBe($existingBalance->id)
            ->and($balance->entitled_days)->toBe(30);
    });

    test('forYear scope filters correctly', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $category = LeaveCategory::create(['name' => 'Annual Leave', 'default_days' => 21]);

        LeaveBalance::create([
            'employee_id' => $employee->id,
            'leave_category_id' => $category->id,
            'year' => 2023,
            'entitled_days' => 21,
        ]);

        LeaveBalance::create([
            'employee_id' => $employee->id,
            'leave_category_id' => $category->id,
            'year' => 2024,
            'entitled_days' => 21,
        ]);

        expect(LeaveBalance::forYear(2024)->count())->toBe(1);
    });
});

describe('LeaveRequest Model', function (): void {
    test('can create a leave request', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $category = LeaveCategory::create(['name' => 'Annual Leave', 'default_days' => 21]);

        $request = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_category_id' => $category->id,
            'proposed_start_date' => '2024-08-01',
            'proposed_end_date' => '2024-08-10',
            'no_requested_days' => 8,
            'leave_reasons' => 'Family vacation',
        ]);

        expect($request->id)->not->toBeNull()
            ->and($request->uuid)->not->toBeNull()
            ->and($request->status)->toBe(LeaveStatus::Pending)
            ->and($request->no_requested_days)->toBe(8)
            ->and($request->is_recalled)->toBeFalse();
    });

    test('leave request status methods work correctly', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $category = LeaveCategory::create(['name' => 'Annual Leave', 'default_days' => 21]);

        $request = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_category_id' => $category->id,
            'proposed_start_date' => '2024-08-01',
            'proposed_end_date' => '2024-08-10',
            'no_requested_days' => 8,
            'status' => LeaveStatus::Pending,
        ]);

        expect($request->isPending())->toBeTrue()
            ->and($request->isVerified())->toBeFalse()
            ->and($request->isApproved())->toBeFalse()
            ->and($request->isRejected())->toBeFalse();
    });

    test('canBeCancelled returns true for pending and verified', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $category = LeaveCategory::create(['name' => 'Annual Leave', 'default_days' => 21]);

        $pendingRequest = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_category_id' => $category->id,
            'proposed_start_date' => '2024-08-01',
            'proposed_end_date' => '2024-08-10',
            'no_requested_days' => 8,
            'status' => LeaveStatus::Pending,
        ]);

        $verifiedRequest = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_category_id' => $category->id,
            'proposed_start_date' => '2024-09-01',
            'proposed_end_date' => '2024-09-10',
            'no_requested_days' => 8,
            'status' => LeaveStatus::Verified,
        ]);

        $approvedRequest = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_category_id' => $category->id,
            'proposed_start_date' => '2024-10-01',
            'proposed_end_date' => '2024-10-10',
            'no_requested_days' => 8,
            'status' => LeaveStatus::Approved,
        ]);

        expect($pendingRequest->canBeCancelled())->toBeTrue()
            ->and($verifiedRequest->canBeCancelled())->toBeTrue()
            ->and($approvedRequest->canBeCancelled())->toBeFalse();
    });

    test('canBeRecalled returns true for approved non-recalled', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $category = LeaveCategory::create(['name' => 'Annual Leave', 'default_days' => 21]);

        $approvedRequest = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_category_id' => $category->id,
            'proposed_start_date' => '2024-08-01',
            'proposed_end_date' => '2024-08-10',
            'no_requested_days' => 8,
            'status' => LeaveStatus::Approved,
            'is_recalled' => false,
        ]);

        $recalledRequest = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_category_id' => $category->id,
            'proposed_start_date' => '2024-09-01',
            'proposed_end_date' => '2024-09-10',
            'no_requested_days' => 8,
            'status' => LeaveStatus::Approved,
            'is_recalled' => true,
        ]);

        expect($approvedRequest->canBeRecalled())->toBeTrue()
            ->and($recalledRequest->canBeRecalled())->toBeFalse();
    });

    test('getEffectiveStartDate returns approved or proposed', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $category = LeaveCategory::create(['name' => 'Annual Leave', 'default_days' => 21]);

        $request = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_category_id' => $category->id,
            'proposed_start_date' => '2024-08-01',
            'proposed_end_date' => '2024-08-10',
            'no_requested_days' => 8,
            'approved_start_date' => '2024-08-05',
            'approved_end_date' => '2024-08-12',
        ]);

        expect($request->getEffectiveStartDate()->format('Y-m-d'))->toBe('2024-08-05');

        $requestWithoutApproval = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_category_id' => $category->id,
            'proposed_start_date' => '2024-09-01',
            'proposed_end_date' => '2024-09-10',
            'no_requested_days' => 8,
        ]);

        expect($requestWithoutApproval->getEffectiveStartDate()->format('Y-m-d'))->toBe('2024-09-01');
    });

    test('pending scope filters correctly', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $category = LeaveCategory::create(['name' => 'Annual Leave', 'default_days' => 21]);

        LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_category_id' => $category->id,
            'proposed_start_date' => '2024-08-01',
            'proposed_end_date' => '2024-08-10',
            'no_requested_days' => 8,
            'status' => LeaveStatus::Pending,
        ]);

        LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_category_id' => $category->id,
            'proposed_start_date' => '2024-09-01',
            'proposed_end_date' => '2024-09-10',
            'no_requested_days' => 8,
            'status' => LeaveStatus::Approved,
        ]);

        expect(LeaveRequest::pending()->count())->toBe(1);
    });

    test('forEmployee scope filters correctly', function (): void {
        $employee1 = Employee::factory()->forTenant($this->tenant->id)->create();
        $employee2 = Employee::factory()->forTenant($this->tenant->id)->create();
        $category = LeaveCategory::create(['name' => 'Annual Leave', 'default_days' => 21]);

        LeaveRequest::create([
            'employee_id' => $employee1->id,
            'leave_category_id' => $category->id,
            'proposed_start_date' => '2024-08-01',
            'proposed_end_date' => '2024-08-10',
            'no_requested_days' => 8,
        ]);

        LeaveRequest::create([
            'employee_id' => $employee2->id,
            'leave_category_id' => $category->id,
            'proposed_start_date' => '2024-09-01',
            'proposed_end_date' => '2024-09-10',
            'no_requested_days' => 8,
        ]);

        expect(LeaveRequest::forEmployee($employee1->id)->count())->toBe(1);
    });

    test('leave request has employee relationship', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $category = LeaveCategory::create(['name' => 'Annual Leave', 'default_days' => 21]);

        $request = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_category_id' => $category->id,
            'proposed_start_date' => '2024-08-01',
            'proposed_end_date' => '2024-08-10',
            'no_requested_days' => 8,
        ]);

        expect($request->employee->id)->toBe($employee->id);
    });

    test('leave request has leave category relationship', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $category = LeaveCategory::create(['name' => 'Annual Leave', 'default_days' => 21]);

        $request = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_category_id' => $category->id,
            'proposed_start_date' => '2024-08-01',
            'proposed_end_date' => '2024-08-10',
            'no_requested_days' => 8,
        ]);

        expect($request->leaveCategory->name)->toBe('Annual Leave');
    });

    test('leave request soft deletes work', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $category = LeaveCategory::create(['name' => 'Annual Leave', 'default_days' => 21]);

        $request = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_category_id' => $category->id,
            'proposed_start_date' => '2024-08-01',
            'proposed_end_date' => '2024-08-10',
            'no_requested_days' => 8,
        ]);

        $request->delete();

        expect(LeaveRequest::count())->toBe(0)
            ->and(LeaveRequest::withTrashed()->count())->toBe(1);
    });
});

describe('LeaveRequest Factory', function (): void {
    test('factory creates valid leave request', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $category = LeaveCategory::factory()->forTenant($this->tenant->id)->create();

        $request = LeaveRequest::factory()
            ->forEmployee($employee)
            ->forCategory($category)
            ->create();

        expect($request->id)->not->toBeNull()
            ->and($request->employee_id)->toBe($employee->id)
            ->and($request->leave_category_id)->toBe($category->id)
            ->and($request->status)->toBe(LeaveStatus::Pending);
    });

    test('factory verified state sets correct attributes', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $category = LeaveCategory::factory()->forTenant($this->tenant->id)->create();

        $request = LeaveRequest::factory()
            ->forEmployee($employee)
            ->forCategory($category)
            ->verified()
            ->create();

        expect($request->status)->toBe(LeaveStatus::Verified)
            ->and($request->supervisor_verified_at)->not->toBeNull();
    });

    test('factory approved state sets correct attributes', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $category = LeaveCategory::factory()->forTenant($this->tenant->id)->create();

        $request = LeaveRequest::factory()
            ->forEmployee($employee)
            ->forCategory($category)
            ->approved()
            ->create();

        expect($request->status)->toBe(LeaveStatus::Approved)
            ->and($request->hod_decision_at)->not->toBeNull()
            ->and($request->no_of_days_approved)->not->toBeNull();
    });

    test('factory rejected state sets correct attributes', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $category = LeaveCategory::factory()->forTenant($this->tenant->id)->create();

        $request = LeaveRequest::factory()
            ->forEmployee($employee)
            ->forCategory($category)
            ->rejected()
            ->create();

        expect($request->status)->toBe(LeaveStatus::Rejected)
            ->and($request->hod_comments)->not->toBeNull();
    });

    test('factory recalled state sets correct attributes', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $category = LeaveCategory::factory()->forTenant($this->tenant->id)->create();

        $request = LeaveRequest::factory()
            ->forEmployee($employee)
            ->forCategory($category)
            ->recalled()
            ->create();

        expect($request->status)->toBe(LeaveStatus::Recalled)
            ->and($request->is_recalled)->toBeTrue()
            ->and($request->recall_reason)->not->toBeNull();
    });
});
