<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\HrmsCore\Models\Attendance\AttendanceRecord;
use App\Modules\HrmsCore\Models\Attendance\Timesheet;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Services\AttendanceService;
use App\Modules\HrmsCore\Services\TimesheetService;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * @param  list<string>  $permissions
 * @return array{0: User, 1: Tenant, 2: Employee}
 */
function createAttendanceContext(array $permissions = ['hrms.attendance.view']): array
{
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(TenantContext::class)->setTenant($tenant);

    foreach (['hrms.attendance.view', 'hrms.attendance.manage', 'hrms.timesheets.approve'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web'], ['category' => 'hrms-core', 'uuid' => (string) Str::uuid()]);
    }
    $user->givePermissionTo($permissions);
    app(ModuleManager::class)->enableForTenant('hrms-core', $tenant);

    $employee = Employee::factory()->forTenant($tenant->id)->create(['user_id' => $user->id]);

    return [$user, $tenant, $employee];
}

describe('Attendance clock in and out', function (): void {
    test('clocks in and out once and computes the hours', function (): void {
        [, , $employee] = createAttendanceContext();
        $service = app(AttendanceService::class);

        Carbon::setTestNow('2026-11-09 08:00:00');
        $service->clockIn($employee);
        expect($service->openSession($employee))->not->toBeNull()
            ->and(fn () => $service->clockIn($employee))->toThrow(DomainException::class, 'already clocked in');

        Carbon::setTestNow('2026-11-09 16:30:00');
        $record = $service->clockOut($employee);

        expect($record->minutesWorked())->toBe(510)
            ->and($service->openSession($employee))->toBeNull()
            ->and(fn () => $service->clockOut($employee))->toThrow(DomainException::class, 'not clocked in')
            ->and($service->minutesOn($employee, today()))->toBe(510);

        Carbon::setTestNow();
    });

    test('HR can record and correct sessions but not invalid ones', function (): void {
        [, , $employee] = createAttendanceContext();
        $service = app(AttendanceService::class);

        $record = $service->recordManual($employee, Carbon::parse('2026-11-02 09:00'), Carbon::parse('2026-11-02 17:00'), 'Forgot to clock', null);
        expect($record->source)->toBe('manual')->and($record->minutesWorked())->toBe(480);

        $service->correct($record, Carbon::parse('2026-11-02 09:00'), Carbon::parse('2026-11-02 18:00'), null);
        expect($record->fresh()->minutesWorked())->toBe(540);

        expect(fn () => $service->recordManual($employee, Carbon::parse('2026-11-03 10:00'), Carbon::parse('2026-11-03 09:00'), null, null))->toThrow(DomainException::class);
        expect(fn () => $service->recordManual($employee, Carbon::parse('2026-11-03 01:00'), Carbon::parse('2026-11-04 08:00'), null, null))->toThrow(DomainException::class, '24 hours');
    });
});

describe('Timesheets', function (): void {
    test('saves hours for the week, submits and gets reviewed by someone else', function (): void {
        [$user, $tenant, $employee] = createAttendanceContext();
        $service = app(TimesheetService::class);
        $sheet = $service->forWeek($employee, Carbon::parse('2026-11-11'));

        expect($sheet->week_start->toDateString())->toBe('2026-11-09');

        $service->saveEntries($sheet, [
            ['work_date' => '2026-11-09', 'hours' => 8, 'project' => 'Alpha'],
            ['work_date' => '2026-11-10', 'hours' => 7.5, 'description' => 'Review'],
            ['work_date' => '2026-11-11', 'hours' => 0],
        ]);
        expect($sheet->fresh()->total_minutes)->toBe(930)->and($sheet->entries()->count())->toBe(2);

        $service->submit($sheet->fresh());
        expect($sheet->fresh()->status)->toBe('submitted')
            ->and(fn () => $service->saveEntries($sheet->fresh(), [['work_date' => '2026-11-09', 'hours' => 1]]))->toThrow(DomainException::class)
            ->and(fn () => $service->approve($sheet->fresh(), 'u', true))->toThrow(DomainException::class, 'own timesheet');

        $service->approve($sheet->fresh(), (string) Str::uuid(), false);
        expect($sheet->fresh()->status)->toBe('approved')
            ->and(fn () => $service->approve($sheet->fresh(), 'u', false))->toThrow(DomainException::class);
    });

    test('rejects hours outside the week or above 24 a day, and empty submissions', function (): void {
        [, , $employee] = createAttendanceContext();
        $service = app(TimesheetService::class);
        $sheet = $service->forWeek($employee, Carbon::parse('2026-11-09'));

        expect(fn () => $service->saveEntries($sheet, [['work_date' => '2026-11-16', 'hours' => 1]]))->toThrow(DomainException::class, 'outside this week')
            ->and(fn () => $service->saveEntries($sheet, [['work_date' => '2026-11-09', 'hours' => 20], ['work_date' => '2026-11-09', 'hours' => 5]]))->toThrow(DomainException::class, '24 hours')
            ->and(fn () => $service->submit($sheet))->toThrow(DomainException::class, 'Add some hours');
    });

    test('a returned timesheet can be corrected and resubmitted, and returning needs a reason', function (): void {
        [, , $employee] = createAttendanceContext();
        $service = app(TimesheetService::class);
        $sheet = $service->forWeek($employee, Carbon::parse('2026-11-09'));
        $service->saveEntries($sheet, [['work_date' => '2026-11-09', 'hours' => 8]]);
        $service->submit($sheet->fresh());

        expect(fn () => $service->reject($sheet->fresh(), 'u', false, '  '))->toThrow(DomainException::class);

        $service->reject($sheet->fresh(), (string) Str::uuid(), false, 'Missing Friday');
        expect($sheet->fresh()->status)->toBe('rejected')->and($sheet->fresh()->isEditable())->toBeTrue();

        $service->saveEntries($sheet->fresh(), [['work_date' => '2026-11-09', 'hours' => 8], ['work_date' => '2026-11-13', 'hours' => 8]]);
        $service->submit($sheet->fresh());
        expect($sheet->fresh()->status)->toBe('submitted')->and($sheet->fresh()->review_comment)->toBeNull();
    });
});

describe('Attendance and timesheet screens', function (): void {
    test('require the attendance permission', function (): void {
        [$user, $tenant] = createAttendanceContext([]);

        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('hrms-core.attendance.index'))->assertForbidden();
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('hrms-core.timesheets.index'))->assertForbidden();
    });

    test('an employee clocks in and out and submits a timesheet from the screens', function (): void {
        [$user, $tenant, $employee] = createAttendanceContext();
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->get(route('hrms-core.attendance.index'))->assertOk()->assertSee('Clock in');
        $this->post(route('hrms-core.attendance.clock-in'))->assertSessionHas('success');
        $this->get(route('hrms-core.attendance.index'))->assertOk()->assertSee('Clock out');
        $this->post(route('hrms-core.attendance.clock-in'))->assertSessionHas('error');
        $this->post(route('hrms-core.attendance.clock-out'))->assertSessionHas('success');

        $weekStart = today()->startOfWeek()->toDateString();
        $this->get(route('hrms-core.timesheets.index'))->assertOk();
        $this->post(route('hrms-core.timesheets.save'), [
            'week' => $weekStart,
            'submit' => 1,
            'entries' => [['work_date' => $weekStart, 'hours' => '7.5', 'project' => 'Alpha']],
        ])->assertSessionHas('success');

        expect(Timesheet::query()->where('employee_id', $employee->id)->value('status'))->toBe('submitted')
            ->and(AttendanceRecord::query()->where('employee_id', $employee->id)->count())->toBe(1);
    });

    test('an approver reviews other people\'s timesheets but not their own', function (): void {
        [$approver, $tenant, $own] = createAttendanceContext(['hrms.attendance.view', 'hrms.timesheets.approve']);
        $other = Employee::factory()->forTenant($tenant->id)->create();
        $service = app(TimesheetService::class);

        $theirs = $service->forWeek($other, today());
        $service->saveEntries($theirs, [['work_date' => today()->startOfWeek()->toDateString(), 'hours' => 8]]);
        $service->submit($theirs->fresh());

        $mine = $service->forWeek($own, today());
        $service->saveEntries($mine, [['work_date' => today()->startOfWeek()->toDateString(), 'hours' => 8]]);
        $service->submit($mine->fresh());

        $this->actingAs($approver)->withSession(['active_tenant_id' => $tenant->id]);
        $this->get(route('hrms-core.timesheets.index'))->assertOk()->assertSee($other->first_name);

        $this->post(route('hrms-core.timesheets.review', $mine->uuid), ['decision' => 'approve'])->assertSessionHas('error');
        expect($mine->fresh()->status)->toBe('submitted');

        $this->post(route('hrms-core.timesheets.review', $theirs->uuid), ['decision' => 'reject'])->assertSessionHasErrors('comment');
        $this->post(route('hrms-core.timesheets.review', $theirs->uuid), ['decision' => 'approve'])->assertSessionHas('success');
        expect($theirs->fresh()->status)->toBe('approved');
    });

    test('only HR with the manage permission can record attendance for others', function (): void {
        [$user, $tenant] = createAttendanceContext();
        $other = Employee::factory()->forTenant($tenant->id)->create();
        $payload = ['employee_id' => $other->id, 'clock_in_at' => '2026-11-02 09:00', 'clock_out_at' => '2026-11-02 17:00'];

        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('hrms-core.attendance.manual'), $payload)->assertForbidden();

        $user->givePermissionTo('hrms.attendance.manage');
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($user->fresh())->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('hrms-core.attendance.manual'), $payload)->assertSessionHas('success');
        expect(AttendanceRecord::query()->where('employee_id', $other->id)->count())->toBe(1);
    });

    test('a tenant cannot see another tenant\'s attendance', function (): void {
        [$user, $tenant] = createAttendanceContext(['hrms.attendance.view', 'hrms.attendance.manage']);
        $foreign = Tenant::factory()->create();
        $foreignEmployee = Employee::factory()->forTenant($foreign->id)->create();
        AttendanceRecord::query()->create(['tenant_id' => $foreign->id, 'employee_id' => $foreignEmployee->id, 'work_date' => today(), 'clock_in_at' => now()]);

        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('hrms-core.attendance.index'))->assertOk()->assertDontSee($foreignEmployee->first_name);
    });
});

describe('Attendance demo seeder', function (): void {
    test('fills attendance and timesheets for employees, and only once', function (): void {
        [, $tenant, $employee] = createAttendanceContext();
        config(['app.demo_tenant' => $tenant->slug]);

        $this->seed(App\Modules\HrmsCore\Database\Seeders\HrmsAttendanceDemoSeeder::class);

        $records = AttendanceRecord::query()->where('employee_id', $employee->id)->count();
        $statuses = Timesheet::query()->where('employee_id', $employee->id)->pluck('status')->all();
        expect($records)->toBeGreaterThan(5)->and($statuses)->toContain('submitted', 'approved');

        $this->seed(App\Modules\HrmsCore\Database\Seeders\HrmsAttendanceDemoSeeder::class);
        expect(AttendanceRecord::query()->where('employee_id', $employee->id)->count())->toBe($records);
    });
});
