<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Database\Seeders;

use App\Models\Tenant;
use App\Modules\HrmsCore\Models\Attendance\AttendanceRecord;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Services\AttendanceService;
use App\Modules\HrmsCore\Services\TimesheetService;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Database\Seeder;

/**
 * Two weeks of clock-in/out sessions and timesheets (one approved, one waiting for
 * review) for the tenant's active employees. Needs at least one employee, so run
 * HrmsCoreDemoSeeder first. Pick the tenant with DEMO_TENANT (default: purpledot).
 * Never runs in production.
 *
 *   DEMO_TENANT=purpledot php artisan db:seed --class="App\Modules\HrmsCore\Database\Seeders\HrmsAttendanceDemoSeeder"
 */
final class HrmsAttendanceDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('HrmsAttendanceDemoSeeder: refusing to write demo attendance into production.');

            return;
        }

        $tenant = Tenant::query()->where('slug', (string) config('app.demo_tenant'))->first();
        if (! $tenant instanceof Tenant) {
            $this->command?->warn('HrmsAttendanceDemoSeeder: tenant not found, skipping.');

            return;
        }

        app(TenantContext::class)->setTenant($tenant);
        setPermissionsTeamId($tenant->id);
        $databases = app(TenantDatabaseManager::class);
        $tenant->requiresDedicatedDb() ? $databases->configure($tenant) : $databases->configureShared();

        $employees = Employee::query()->where('tenant_id', $tenant->id)->where('is_active', true)->get();
        if ($employees->isEmpty()) {
            $this->command?->warn('HrmsAttendanceDemoSeeder: no employees yet, run HrmsCoreDemoSeeder first.');

            return;
        }

        $attendance = app(AttendanceService::class);
        $timesheets = app(TimesheetService::class);

        foreach ($employees as $employee) {
            if (AttendanceRecord::query()->where('employee_id', $employee->id)->where('source', 'manual')->where('note', 'Demo data')->exists()) {
                continue;
            }

            foreach (range(1, 14) as $daysAgo) {
                $day = today()->subDays($daysAgo);
                if ($day->isWeekend()) {
                    continue;
                }
                $late = $daysAgo % 4 === 0 ? 40 : 0;
                $attendance->recordManual($employee, $day->copy()->setTime(8, 0)->addMinutes($late), $day->copy()->setTime(17, 0), 'Demo data', null);
            }

            $lastWeek = $timesheets->forWeek($employee, today()->subWeek());
            $timesheets->saveEntries($lastWeek, $this->weekRows($lastWeek->week_start, 'Client support'));
            $timesheets->submit($lastWeek);

            $earlier = $timesheets->forWeek($employee, today()->subWeeks(2));
            $timesheets->saveEntries($earlier, $this->weekRows($earlier->week_start, 'Platform development'));
            $timesheets->submit($earlier);
            $timesheets->approve($earlier->refresh(), null, false, 'Looks right (demo)');
        }

        $this->command?->info('HrmsAttendanceDemoSeeder: attendance and timesheets created for '.$employees->count().' employee(s).');
    }

    /**
     * @return list<array{work_date: string, hours: int, project: string, description: string}>
     */
    private function weekRows(\Carbon\CarbonInterface $weekStart, string $project): array
    {
        return array_map(fn (int $offset): array => [
            'work_date' => $weekStart->copy()->addDays($offset)->toDateString(),
            'hours' => 8,
            'project' => $project,
            'description' => 'Demo entry',
        ], range(0, 4));
    }
}
