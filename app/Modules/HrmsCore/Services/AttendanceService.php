<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Services;

use App\Modules\HrmsCore\Models\Attendance\AttendanceRecord;
use App\Modules\HrmsCore\Models\Employees\Employee;
use Carbon\CarbonInterface;
use DomainException;

/**
 * Clock-in and clock-out sessions. An employee can only have one open session.
 */
final class AttendanceService
{
    public function openSession(Employee $employee): ?AttendanceRecord
    {
        return AttendanceRecord::query()
            ->where('tenant_id', $employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->whereNull('clock_out_at')
            ->latest('clock_in_at')
            ->first();
    }

    public function clockIn(Employee $employee, ?string $note = null, ?string $userUuid = null): AttendanceRecord
    {
        if ($this->openSession($employee) instanceof AttendanceRecord) {
            throw new DomainException('You are already clocked in. Clock out first.');
        }

        return AttendanceRecord::query()->create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'work_date' => today()->toDateString(),
            'clock_in_at' => now(),
            'source' => 'web',
            'note' => $note,
            'created_by' => $userUuid,
        ]);
    }

    public function clockOut(Employee $employee): AttendanceRecord
    {
        $session = $this->openSession($employee);

        if (! $session instanceof AttendanceRecord) {
            throw new DomainException('You are not clocked in.');
        }

        $session->update(['clock_out_at' => now()]);

        return $session;
    }

    /** HR records or corrects a session on someone's behalf. */
    public function recordManual(Employee $employee, CarbonInterface $in, CarbonInterface $out, ?string $note, ?string $userUuid): AttendanceRecord
    {
        $this->assertValidSession($in, $out);

        return AttendanceRecord::query()->create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'work_date' => $in->toDateString(),
            'clock_in_at' => $in,
            'clock_out_at' => $out,
            'source' => 'manual',
            'note' => $note,
            'created_by' => $userUuid,
        ]);
    }

    public function correct(AttendanceRecord $record, CarbonInterface $in, CarbonInterface $out, ?string $note): AttendanceRecord
    {
        $this->assertValidSession($in, $out);

        $record->update([
            'work_date' => $in->toDateString(),
            'clock_in_at' => $in,
            'clock_out_at' => $out,
            'source' => 'manual',
            'note' => $note ?? $record->note,
        ]);

        return $record;
    }

    /** Total minutes worked by the employee on a day, counting an open session up to now. */
    public function minutesOn(Employee $employee, CarbonInterface $day): int
    {
        return (int) AttendanceRecord::query()
            ->where('tenant_id', $employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $day->toDateString())
            ->get()
            ->sum(fn (AttendanceRecord $record): int => $record->minutesWorked());
    }

    private function assertValidSession(CarbonInterface $in, CarbonInterface $out): void
    {
        if ($out->lte($in)) {
            throw new DomainException('The clock-out time must be after the clock-in time.');
        }

        if ($in->diffInHours($out) > 24) {
            throw new DomainException('A single session cannot be longer than 24 hours.');
        }
    }
}
