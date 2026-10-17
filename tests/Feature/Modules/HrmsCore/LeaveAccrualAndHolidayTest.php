<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Models\Leave\Holiday;
use App\Modules\HrmsCore\Models\Leave\LeaveBalance;
use App\Modules\HrmsCore\Models\Leave\LeaveCategory;
use App\Modules\HrmsCore\Models\Leave\LeaveRequest;
use App\Modules\HrmsCore\Services\LeaveAccrualService;
use App\Modules\HrmsCore\Services\LeaveCalculationService;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * @return array{0: User, 1: Tenant}
 */
function createLeaveTestContext(): array
{
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(TenantContext::class)->setTenant($tenant);

    $permissions = [
        'hrms.leave.view',
        'hrms.leave.request',
        'hrms.leave.approve',
        'hrms.leave.manage_holidays',
        'hrms.employees.view',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'category' => 'hrms-core',
            'guard_name' => 'web',
        ], [
            'uuid' => (string) Str::uuid(),
        ]);
    }

    $user->givePermissionTo($permissions);
    app(ModuleManager::class)->enableForTenant('hrms-core', $tenant);

    return [$user, $tenant];
}

describe('Leave Calculation Service (Weekends, Holidays & Half Days)', function (): void {
    test('excludes weekends from working days calculation', function (): void {
        [$user, $tenant] = createLeaveTestContext();
        $service = app(LeaveCalculationService::class);

        // 2026-10-19 is Monday, 2026-10-25 is Sunday (7 calendar days, 5 working days)
        $monday = Carbon::parse('2026-10-19');
        $sunday = Carbon::parse('2026-10-25');

        $workingDays = $service->calculateWorkingDays($monday, $sunday, $tenant->id);

        expect($workingDays)->toBe(5.0);
    });

    test('excludes active public holidays spanning the leave period', function (): void {
        [$user, $tenant] = createLeaveTestContext();
        $service = app(LeaveCalculationService::class);

        // Monday to Friday: 2026-10-19 to 2026-10-23 (5 calendar days, 5 weekdays)
        $monday = Carbon::parse('2026-10-19');
        $friday = Carbon::parse('2026-10-23');

        // Wednesday is an active public holiday
        Holiday::create([
            'tenant_id' => $tenant->id,
            'name' => 'Mid-Week National Holiday',
            'date' => '2026-10-21',
            'is_recurring' => false,
            'is_active' => true,
        ]);

        $workingDays = $service->calculateWorkingDays($monday, $friday, $tenant->id);

        // 5 weekdays minus 1 public holiday = 4 working days
        expect($workingDays)->toBe(4.0);
    });

    test('excludes annual recurring holidays matching month and day', function (): void {
        [$user, $tenant] = createLeaveTestContext();
        $service = app(LeaveCalculationService::class);

        // Thursday to Monday: 2026-12-24 (Thu) to 2026-12-28 (Mon)
        // Weekdays: Thu (24th), Fri (25th), Mon (28th) = 3 weekdays
        $thu = Carbon::parse('2026-12-24');
        $mon = Carbon::parse('2026-12-28');

        // Recurring Christmas Day holiday on 25th of December (registered in a prior year)
        Holiday::create([
            'tenant_id' => $tenant->id,
            'name' => 'Christmas Day',
            'date' => '2020-12-25',
            'is_recurring' => true,
            'is_active' => true,
        ]);

        $workingDays = $service->calculateWorkingDays($thu, $mon, $tenant->id);

        // 3 weekdays minus 1 recurring holiday (Fri Dec 25) = 2 working days
        expect($workingDays)->toBe(2.0);
    });

    test('does not double-deduct when a holiday falls on a weekend', function (): void {
        [$user, $tenant] = createLeaveTestContext();
        $service = app(LeaveCalculationService::class);

        // Monday 2026-10-19 to Sunday 2026-10-25 (5 weekdays, 2 weekend days)
        $monday = Carbon::parse('2026-10-19');
        $sunday = Carbon::parse('2026-10-25');

        // Holiday falls on Saturday 2026-10-24
        Holiday::create([
            'tenant_id' => $tenant->id,
            'name' => 'Weekend Holiday',
            'date' => '2026-10-24',
            'is_recurring' => false,
            'is_active' => true,
        ]);

        $workingDays = $service->calculateWorkingDays($monday, $sunday, $tenant->id);

        // Should still be exactly 5 working days (the weekend holiday doesn't reduce weekdays)
        expect($workingDays)->toBe(5.0);
    });

    test('handles half-day leave calculations accurately', function (): void {
        [$user, $tenant] = createLeaveTestContext();
        $service = app(LeaveCalculationService::class);

        // Single Tuesday: working day
        $tuesday = Carbon::parse('2026-10-20');
        $halfDayWorking = $service->calculateWorkingDays($tuesday, $tuesday, $tenant->id, isHalfDay: true);
        expect($halfDayWorking)->toBe(0.5);

        // Single Saturday: weekend
        $saturday = Carbon::parse('2026-10-24');
        $halfDayWeekend = $service->calculateWorkingDays($saturday, $saturday, $tenant->id, isHalfDay: true);
        expect($halfDayWeekend)->toBe(0.0);

        // Single weekday that is a public holiday
        Holiday::create([
            'tenant_id' => $tenant->id,
            'name' => 'Holiday Tuesday',
            'date' => '2026-10-20',
            'is_recurring' => false,
            'is_active' => true,
        ]);
        $halfDayHoliday = $service->calculateWorkingDays($tuesday, $tuesday, $tenant->id, isHalfDay: true);
        expect($halfDayHoliday)->toBe(0.0);
    });
});

describe('Leave Request Submission & Balance Parity', function (): void {
    test('submitting a leave spanning a holiday calculates working days and holds pending balance', function (): void {
        [$user, $tenant] = createLeaveTestContext();

        $employee = Employee::factory()->forTenant($tenant->id)->create(['is_active' => true]);
        $category = LeaveCategory::create([
            'tenant_id' => $tenant->id,
            'name' => 'Annual Leave',
            'default_days' => 20,
        ]);

        // Holiday on Wednesday
        Holiday::create([
            'tenant_id' => $tenant->id,
            'name' => 'Midweek Holiday',
            'date' => '2026-11-18',
            'is_recurring' => false,
            'is_active' => true,
        ]);

        // Monday 2026-11-16 to Friday 2026-11-20 (5 calendar days, 4 working days due to holiday)
        $response = test()->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('hrms-core.leave-requests.store'), [
                'employee_id' => $employee->id,
                'leave_category_id' => $category->id,
                'proposed_start_date' => '2026-11-16',
                'proposed_end_date' => '2026-11-20',
                'leave_reasons' => 'Rest and family visit',
            ]);

        $response->assertRedirect(route('hrms-core.leave-requests.index'))
            ->assertSessionHas('status', 'success');

        $leaveRequest = LeaveRequest::where('employee_id', $employee->id)->firstOrFail();
        expect($leaveRequest->no_requested_days)->toEqual(4.0);

        $balance = LeaveBalance::where([
            'employee_id' => $employee->id,
            'leave_category_id' => $category->id,
            'year' => 2026,
        ])->firstOrFail();

        expect($balance->entitled_days)->toEqual(20.0)
            ->and($balance->pending_days)->toEqual(4.0)
            ->and($balance->used_days)->toEqual(0.0)
            ->and($balance->remaining_days)->toEqual(16.0);

        // Approve converts pending to used
        test()->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('hrms-core.leave-requests.approve', $leaveRequest), [
                'comments' => 'Approved by supervisor',
            ]);

        $balance->refresh();
        expect($balance->pending_days)->toEqual(0.0)
            ->and($balance->used_days)->toEqual(4.0)
            ->and($balance->remaining_days)->toEqual(16.0);
    });

    test('half-day leave deducts 0.5 days from balance and converts on approval', function (): void {
        [$user, $tenant] = createLeaveTestContext();

        $employee = Employee::factory()->forTenant($tenant->id)->create(['is_active' => true]);
        $category = LeaveCategory::create([
            'tenant_id' => $tenant->id,
            'name' => 'Annual Leave',
            'default_days' => 20,
        ]);

        // Submit single-day request marked as half-day (Tuesday 2026-10-20)
        $response = test()->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('hrms-core.leave-requests.store'), [
                'employee_id' => $employee->id,
                'leave_category_id' => $category->id,
                'proposed_start_date' => '2026-10-20',
                'proposed_end_date' => '2026-10-20',
                'is_half_day' => '1',
                'leave_reasons' => 'Afternoon doctor appointment',
            ]);

        $response->assertRedirect(route('hrms-core.leave-requests.index'));

        $leaveRequest = LeaveRequest::where('employee_id', $employee->id)->firstOrFail();
        expect($leaveRequest->no_requested_days)->toEqual(0.5);

        $balance = LeaveBalance::where([
            'employee_id' => $employee->id,
            'leave_category_id' => $category->id,
            'year' => 2026,
        ])->firstOrFail();

        expect($balance->pending_days)->toEqual(0.5)
            ->and($balance->remaining_days)->toEqual(19.5);

        // Approve converts 0.5 pending to used
        test()->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('hrms-core.leave-requests.approve', $leaveRequest), [
                'comments' => 'Approved doctor visit',
            ]);

        $balance->refresh();
        expect($balance->pending_days)->toEqual(0.0)
            ->and($balance->used_days)->toEqual(0.5)
            ->and($balance->remaining_days)->toEqual(19.5);
    });

    test('server strictly computes requested days and ignores client-supplied tampered override', function (): void {
        [$user, $tenant] = createLeaveTestContext();

        $employee = Employee::factory()->forTenant($tenant->id)->create(['is_active' => true]);
        $category = LeaveCategory::create([
            'tenant_id' => $tenant->id,
            'name' => 'Annual Leave',
            'default_days' => 20,
        ]);

        // Range is Monday 2026-11-02 to Friday 2026-11-06 (5 full working days)
        // Client maliciously supplies tampered 'no_requested_days' => 1
        $response = test()->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('hrms-core.leave-requests.store'), [
                'employee_id' => $employee->id,
                'leave_category_id' => $category->id,
                'proposed_start_date' => '2026-11-02',
                'proposed_end_date' => '2026-11-06',
                'no_requested_days' => 1,
            ]);

        $response->assertRedirect(route('hrms-core.leave-requests.index'));

        $leaveRequest = LeaveRequest::where('employee_id', $employee->id)->firstOrFail();
        // Server must ignore client's '1' and compute 5.0
        expect($leaveRequest->no_requested_days)->toEqual(5.0);

        $balance = LeaveBalance::where('employee_id', $employee->id)->firstOrFail();
        expect($balance->pending_days)->toEqual(5.0)
            ->and($balance->remaining_days)->toEqual(15.0);
    });

    test('leave spanning 31 December splits days across the two years balances', function (): void {
        [$user, $tenant] = createLeaveTestContext();

        $employee = Employee::factory()->forTenant($tenant->id)->create(['is_active' => true]);
        $category = LeaveCategory::create([
            'tenant_id' => $tenant->id,
            'name' => 'Annual Leave',
            'default_days' => 20,
        ]);

        // Recurring New Year's Day holiday on Jan 1
        Holiday::create([
            'tenant_id' => $tenant->id,
            'name' => 'New Years Day',
            'date' => '2020-01-01',
            'is_recurring' => true,
            'is_active' => true,
        ]);

        // Wednesday 2026-12-30 to Monday 2027-01-04
        // 2026 portion: Dec 30 (Wed), Dec 31 (Thu) = 2 working days
        // 2027 portion: Jan 1 (Fri) is Holiday. Jan 2 (Sat), Jan 3 (Sun) are weekend. Jan 4 (Mon) = 1 working day
        // Total requested = 3 working days (2 in 2026, 1 in 2027)
        $response = test()->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('hrms-core.leave-requests.store'), [
                'employee_id' => $employee->id,
                'leave_category_id' => $category->id,
                'proposed_start_date' => '2026-12-30',
                'proposed_end_date' => '2027-01-04',
            ]);

        $response->assertRedirect(route('hrms-core.leave-requests.index'));

        $leaveRequest = LeaveRequest::where('employee_id', $employee->id)->firstOrFail();
        expect($leaveRequest->no_requested_days)->toEqual(3.0);

        // Check 2026 balance
        $balance2026 = LeaveBalance::where([
            'employee_id' => $employee->id,
            'year' => 2026,
        ])->firstOrFail();
        expect($balance2026->pending_days)->toEqual(2.0)
            ->and($balance2026->remaining_days)->toEqual(18.0);

        // Check 2027 balance
        $balance2027 = LeaveBalance::where([
            'employee_id' => $employee->id,
            'year' => 2027,
        ])->firstOrFail();
        expect($balance2027->pending_days)->toEqual(1.0)
            ->and($balance2027->remaining_days)->toEqual(19.0);

        // Approve request
        test()->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('hrms-core.leave-requests.approve', $leaveRequest), [
                'comments' => 'Approved year-end holiday leave',
            ]);

        $balance2026->refresh();
        $balance2027->refresh();

        expect($balance2026->pending_days)->toEqual(0.0)
            ->and($balance2026->used_days)->toEqual(2.0)
            ->and($balance2026->remaining_days)->toEqual(18.0);

        expect($balance2027->pending_days)->toEqual(0.0)
            ->and($balance2027->used_days)->toEqual(1.0)
            ->and($balance2027->remaining_days)->toEqual(19.0);
    });

    test('rejecting a leave restores pending days to the balance', function (): void {
        [$user, $tenant] = createLeaveTestContext();

        $employee = Employee::factory()->forTenant($tenant->id)->create(['is_active' => true]);
        $category = LeaveCategory::create([
            'tenant_id' => $tenant->id,
            'name' => 'Casual Leave',
            'default_days' => 10,
        ]);

        $response = test()->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('hrms-core.leave-requests.store'), [
                'employee_id' => $employee->id,
                'leave_category_id' => $category->id,
                'proposed_start_date' => '2026-11-02',
                'proposed_end_date' => '2026-11-04',
            ]);

        $response->assertRedirect(route('hrms-core.leave-requests.index'));

        $leaveRequest = LeaveRequest::where('employee_id', $employee->id)->firstOrFail();
        $balance = LeaveBalance::where('employee_id', $employee->id)->firstOrFail();
        expect($balance->pending_days)->toEqual(3.0);

        // Reject
        test()->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('hrms-core.leave-requests.reject', $leaveRequest), [
                'comments' => 'Staffing constraints',
            ]);

        $balance->refresh();
        expect($balance->pending_days)->toEqual(0.0)
            ->and($balance->used_days)->toEqual(0.0)
            ->and($balance->remaining_days)->toEqual(10.0);
    });

    test('insufficient balance rejects leave submission with error', function (): void {
        [$user, $tenant] = createLeaveTestContext();

        $employee = Employee::factory()->forTenant($tenant->id)->create(['is_active' => true]);
        $category = LeaveCategory::create([
            'tenant_id' => $tenant->id,
            'name' => 'Short Leave',
            'default_days' => 2,
        ]);

        // Request 5 days with only 2 days entitled
        $response = test()->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('hrms-core.leave-requests.store'), [
                'employee_id' => $employee->id,
                'leave_category_id' => $category->id,
                'proposed_start_date' => '2026-11-02',
                'proposed_end_date' => '2026-11-06',
            ]);

        $response->assertSessionHasErrors(['leave_category_id']);
        expect(LeaveRequest::where('employee_id', $employee->id)->count())->toBe(0);
    });
});

describe('Year-End Leave Balance Rollover', function (): void {
    test('rolls over unused leave balance to next calendar year subject to policy cap', function (): void {
        [$user, $tenant] = createLeaveTestContext();

        $employee = Employee::factory()->forTenant($tenant->id)->create(['is_active' => true]);
        $category = LeaveCategory::create([
            'tenant_id' => $tenant->id,
            'name' => 'Annual Leave',
            'default_days' => 21,
        ]);

        // In 2025: employee had 21 entitled days, used 7 days -> 14 days remaining
        LeaveBalance::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'leave_category_id' => $category->id,
            'year' => 2025,
            'entitled_days' => 21,
            'carried_forward_days' => 0,
            'used_days' => 7,
            'pending_days' => 0,
        ]);

        $accrualService = app(LeaveAccrualService::class);
        // Execute rollover with max carry-forward of 10 days
        $processed = $accrualService->rolloverYear(2025, 2026, $tenant->id, maxCarryForward: 10);

        expect($processed)->toBe(1);

        $newBalance = LeaveBalance::where([
            'employee_id' => $employee->id,
            'leave_category_id' => $category->id,
            'year' => 2026,
        ])->firstOrFail();

        // New year entitlement = 21, carried forward capped at 10 (out of 14 unused)
        expect($newBalance->entitled_days)->toEqual(21.0)
            ->and($newBalance->carried_forward_days)->toEqual(10.0)
            ->and($newBalance->used_days)->toEqual(0.0)
            ->and($newBalance->pending_days)->toEqual(0.0)
            ->and($newBalance->remaining_days)->toEqual(31.0);
    });

    test('rollover is safe to re-run and preserves approved used_days and pending_days mid-year', function (): void {
        [$user, $tenant] = createLeaveTestContext();

        $employee = Employee::factory()->forTenant($tenant->id)->create(['is_active' => true]);
        $category = LeaveCategory::create([
            'tenant_id' => $tenant->id,
            'name' => 'Annual Leave',
            'default_days' => 20,
        ]);

        // In 2025: employee had 20 days, used 10 -> 10 unused
        LeaveBalance::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'leave_category_id' => $category->id,
            'year' => 2025,
            'entitled_days' => 20.0,
            'carried_forward_days' => 0.0,
            'used_days' => 10.0,
            'pending_days' => 0.0,
        ]);

        $accrualService = app(LeaveAccrualService::class);

        // Run 1: beginning of year 2026 rollover
        $accrualService->rolloverYear(2025, 2026, $tenant->id, 10);

        $balance2026 = LeaveBalance::where([
            'employee_id' => $employee->id,
            'year' => 2026,
        ])->firstOrFail();

        expect($balance2026->entitled_days)->toEqual(20.0)
            ->and($balance2026->carried_forward_days)->toEqual(10.0)
            ->and($balance2026->used_days)->toEqual(0.0)
            ->and($balance2026->remaining_days)->toEqual(30.0);

        // Mid-year: employee takes and has 5 days approved, and 2 days pending
        $balance2026->update([
            'used_days' => 5.0,
            'pending_days' => 2.0,
        ]);
        $balance2026->refresh();
        expect($balance2026->remaining_days)->toEqual(23.0);

        // Run 2: mid-year re-run of rollover command
        $accrualService->rolloverYear(2025, 2026, $tenant->id, 10);

        $balance2026->refresh();
        // Crucial invariant: used_days and pending_days MUST NOT be reset to 0
        expect($balance2026->used_days)->toEqual(5.0)
            ->and($balance2026->pending_days)->toEqual(2.0)
            ->and($balance2026->carried_forward_days)->toEqual(10.0)
            ->and($balance2026->remaining_days)->toEqual(23.0);
    });

    test('rollover respects per-category carry-forward cap with fallback to default', function (): void {
        [$user, $tenant] = createLeaveTestContext();

        $employee = Employee::factory()->forTenant($tenant->id)->create(['is_active' => true]);

        // Category A: explicit cap of 5 days
        $catA = LeaveCategory::create([
            'tenant_id' => $tenant->id,
            'name' => 'Restricted Leave',
            'default_days' => 20,
            'max_carry_forward_days' => 5,
        ]);

        // Category B: explicit cap of 15 days
        $catB = LeaveCategory::create([
            'tenant_id' => $tenant->id,
            'name' => 'Generous Leave',
            'default_days' => 25,
            'max_carry_forward_days' => 15,
        ]);

        // Category C: null cap -> falls back to default (10)
        $catC = LeaveCategory::create([
            'tenant_id' => $tenant->id,
            'name' => 'Standard Leave',
            'default_days' => 21,
            'max_carry_forward_days' => null,
        ]);

        // Each has 14 unused days in 2025
        foreach ([$catA, $catB, $catC] as $cat) {
            LeaveBalance::create([
                'tenant_id' => $tenant->id,
                'employee_id' => $employee->id,
                'leave_category_id' => $cat->id,
                'year' => 2025,
                'entitled_days' => 20.0,
                'carried_forward_days' => 0.0,
                'used_days' => 6.0,
                'pending_days' => 0.0,
            ]);
        }

        $accrualService = app(LeaveAccrualService::class);
        $accrualService->rolloverYear(2025, 2026, $tenant->id, maxCarryForward: 10);

        $balA = LeaveBalance::where(['employee_id' => $employee->id, 'leave_category_id' => $catA->id, 'year' => 2026])->firstOrFail();
        $balB = LeaveBalance::where(['employee_id' => $employee->id, 'leave_category_id' => $catB->id, 'year' => 2026])->firstOrFail();
        $balC = LeaveBalance::where(['employee_id' => $employee->id, 'leave_category_id' => $catC->id, 'year' => 2026])->firstOrFail();

        // Cat A capped at 5
        expect($balA->carried_forward_days)->toEqual(5.0);
        // Cat B allowed up to 15, so carries all 14 unused
        expect($balB->carried_forward_days)->toEqual(14.0);
        // Cat C capped at default 10
        expect($balC->carried_forward_days)->toEqual(10.0);
    });

    test('prorates leave entitlement for mid-year joiners', function (): void {
        $accrualService = app(LeaveAccrualService::class);

        // Employee joining in July (month 7) with 24 annual days:
        // Remaining months: Jul, Aug, Sep, Oct, Nov, Dec = 6 months
        // Prorated = (24 / 12) * 6 = 12.0 days
        $julyJoiner = Carbon::parse('2026-07-01');
        $prorated = $accrualService->calculateProratedEntitlement(24.0, $julyJoiner, 2026);
        expect($prorated)->toEqual(12.0);

        // Employee joining in October (month 10) with 24 annual days:
        // Remaining months: Oct, Nov, Dec = 3 months
        // Prorated = (24 / 12) * 3 = 6.0 days
        $octJoiner = Carbon::parse('2026-10-15');
        expect($accrualService->calculateProratedEntitlement(24.0, $octJoiner, 2026))->toEqual(6.0);

        // Employee joining in a prior year gets full entitlement
        $priorJoiner = Carbon::parse('2024-03-01');
        expect($accrualService->calculateProratedEntitlement(24.0, $priorJoiner, 2026))->toEqual(24.0);
    });

    test('rollover excludes inactive or terminated employees', function (): void {
        [$user, $tenant] = createLeaveTestContext();

        $inactiveEmployee = Employee::factory()->forTenant($tenant->id)->create(['is_active' => false]);
        $category = LeaveCategory::create([
            'tenant_id' => $tenant->id,
            'name' => 'Annual Leave',
            'default_days' => 21,
        ]);

        LeaveBalance::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $inactiveEmployee->id,
            'leave_category_id' => $category->id,
            'year' => 2025,
            'entitled_days' => 21,
            'carried_forward_days' => 0,
            'used_days' => 0,
            'pending_days' => 0,
        ]);

        $accrualService = app(LeaveAccrualService::class);
        $processed = $accrualService->rolloverYear(2025, 2026, $tenant->id, 10);

        expect($processed)->toBe(0);
        expect(LeaveBalance::where('employee_id', $inactiveEmployee->id)->where('year', 2026)->exists())->toBeFalse();
    });

    test('artisan command hrms:leave-rollover runs successfully', function (): void {
        [$user, $tenant] = createLeaveTestContext();

        $employee = Employee::factory()->forTenant($tenant->id)->create(['is_active' => true]);
        $category = LeaveCategory::create([
            'tenant_id' => $tenant->id,
            'name' => 'Study Leave',
            'default_days' => 15,
        ]);

        LeaveBalance::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'leave_category_id' => $category->id,
            'year' => 2025,
            'entitled_days' => 15,
            'carried_forward_days' => 0,
            'used_days' => 5,
            'pending_days' => 0,
        ]);

        $exitCode = Artisan::call('hrms:leave-rollover', [
            'fromYear' => 2025,
            'toYear' => 2026,
            '--tenant' => $tenant->id,
            '--max-carry' => 5,
        ]);

        expect($exitCode)->toBe(0);

        $newBalance = LeaveBalance::where([
            'employee_id' => $employee->id,
            'year' => 2026,
        ])->firstOrFail();

        expect($newBalance->carried_forward_days)->toBe(5);
    });
});

describe('Holiday Management Web UI & Authorization', function (): void {
    test('can list, create, update and delete public holidays', function (): void {
        [$user, $tenant] = createLeaveTestContext();

        // 1. Index page
        $indexRes = test()->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('hrms-core.holidays.index'));
        $indexRes->assertOk()->assertSee('Public Holidays Management');

        // 2. Store holiday
        $storeRes = test()->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('hrms-core.holidays.store'), [
                'name' => 'Founders Day',
                'date' => '2026-08-04',
                'description' => 'National memorial day',
                'is_recurring' => '1',
                'is_active' => '1',
            ]);

        $storeRes->assertRedirect(route('hrms-core.holidays.index'))
            ->assertSessionHas('status', 'success');

        $holiday = Holiday::where('name', 'Founders Day')->firstOrFail();
        expect($holiday->date->format('Y-m-d'))->toBe('2026-08-04')
            ->and($holiday->is_recurring)->toBeTrue()
            ->and($holiday->tenant_id)->toBe($tenant->id);

        // 3. Update holiday
        $updateRes = test()->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->put(route('hrms-core.holidays.update', $holiday), [
                'name' => 'Founders Day (Observed)',
                'date' => '2026-08-05',
                'description' => 'Updated date',
                'is_recurring' => '0',
                'is_active' => '1',
            ]);

        $updateRes->assertRedirect(route('hrms-core.holidays.index'))
            ->assertSessionHas('status', 'success');

        expect($holiday->fresh()->name)->toBe('Founders Day (Observed)')
            ->and($holiday->fresh()->date->format('Y-m-d'))->toBe('2026-08-05');

        // 4. Delete holiday
        $deleteRes = test()->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->delete(route('hrms-core.holidays.destroy', $holiday));

        $deleteRes->assertRedirect(route('hrms-core.holidays.index'))
            ->assertSessionHas('status', 'success');

        expect(Holiday::where('id', $holiday->id)->exists())->toBeFalse();
    });

    test('adversarial: cannot update or delete holiday belonging to another tenant (404)', function (): void {
        [$user, $tenant] = createLeaveTestContext();

        $otherTenant = Tenant::factory()->create();
        $foreignHoliday = Holiday::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Holiday',
            'date' => '2026-07-01',
            'is_active' => true,
        ]);

        $res = test()->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->put(route('hrms-core.holidays.update', $foreignHoliday), [
                'name' => 'Hijacked Holiday',
                'date' => '2026-07-01',
            ]);

        expect(in_array($res->status(), [403, 404], true))->toBeTrue();
        expect($foreignHoliday->fresh()->name)->toBe('Foreign Holiday');
    });

    test('adversarial: user without manage_holidays permission is rejected with 403', function (): void {
        [$user, $tenant] = createLeaveTestContext();
        $user->revokePermissionTo('hrms.leave.manage_holidays');

        $res = test()->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('hrms-core.holidays.store'), [
                'name' => 'Unauthorized Holiday',
                'date' => '2026-05-01',
            ]);

        $res->assertStatus(403);
    });

    test('validation: cannot create duplicate holiday on same date for same tenant', function (): void {
        [$user, $tenant] = createLeaveTestContext();

        Holiday::create([
            'tenant_id' => $tenant->id,
            'name' => 'Workers Day',
            'date' => '2026-05-01',
            'is_active' => true,
        ]);

        $res = test()->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('hrms-core.holidays.store'), [
                'name' => 'Workers Day',
                'date' => '2026-05-01',
            ]);

        $res->assertSessionHasErrors(['name' => 'A holiday with this name on the selected date already exists.']);
    });
});
