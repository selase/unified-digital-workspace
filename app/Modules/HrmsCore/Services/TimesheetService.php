<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Services;

use App\Modules\HrmsCore\Models\Attendance\Timesheet;
use App\Modules\HrmsCore\Models\Attendance\TimesheetEntry;
use App\Modules\HrmsCore\Models\Employees\Employee;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

final class TimesheetService
{
    public function weekStart(CarbonInterface $date): CarbonInterface
    {
        return $date->copy()->startOfWeek();
    }

    public function forWeek(Employee $employee, CarbonInterface $date): Timesheet
    {
        $sheet = Timesheet::query()->firstOrCreate([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'week_start' => $this->weekStart($date)->toDateString(),
        ], ['status' => Timesheet::STATUS_DRAFT]);

        return $sheet->wasRecentlyCreated ? $sheet->refresh() : $sheet;
    }

    /**
     * Replaces the sheet's entries. Rows with no time are ignored.
     *
     * @param  list<array{work_date: string, hours: float|int|string|null, project?: string|null, description?: string|null}>  $rows
     */
    public function saveEntries(Timesheet $timesheet, array $rows): Timesheet
    {
        if (! $timesheet->isEditable()) {
            throw new DomainException('This timesheet has been submitted and can no longer be edited.');
        }

        $weekEnd = $timesheet->week_start->copy()->addDays(6);
        $perDay = [];
        $clean = [];

        foreach ($rows as $row) {
            $minutes = (int) round(((float) ($row['hours'] ?? 0)) * 60);
            if ($minutes <= 0) {
                continue;
            }

            $date = \Illuminate\Support\Carbon::parse($row['work_date']);
            if ($date->lt($timesheet->week_start) || $date->gt($weekEnd)) {
                throw new DomainException('An entry falls outside this week.');
            }

            $key = $date->toDateString();
            $perDay[$key] = ($perDay[$key] ?? 0) + $minutes;
            if ($perDay[$key] > 24 * 60) {
                throw new DomainException('A day cannot have more than 24 hours.');
            }

            $clean[] = ['timesheet_id' => $timesheet->id, 'work_date' => $key, 'minutes' => $minutes, 'project' => $row['project'] ?? null, 'description' => $row['description'] ?? null];
        }

        return DB::connection('tenant')->transaction(function () use ($timesheet, $clean): Timesheet {
            TimesheetEntry::query()->where('timesheet_id', $timesheet->id)->delete();
            foreach ($clean as $entry) {
                TimesheetEntry::query()->create($entry);
            }
            $timesheet->update(['total_minutes' => array_sum(array_column($clean, 'minutes'))]);

            return $timesheet;
        });
    }

    public function submit(Timesheet $timesheet): Timesheet
    {
        if (! $timesheet->isEditable()) {
            throw new DomainException('Only a draft or rejected timesheet can be submitted.');
        }

        if ($timesheet->total_minutes <= 0) {
            throw new DomainException('Add some hours before submitting.');
        }

        $timesheet->update(['status' => Timesheet::STATUS_SUBMITTED, 'submitted_at' => now(), 'review_comment' => null, 'reviewed_by' => null, 'reviewed_at' => null]);

        return $timesheet;
    }

    public function approve(Timesheet $timesheet, ?string $reviewerUserUuid, bool $isOwnSheet, ?string $comment = null): Timesheet
    {
        return $this->review($timesheet, Timesheet::STATUS_APPROVED, $reviewerUserUuid, $isOwnSheet, $comment);
    }

    public function reject(Timesheet $timesheet, ?string $reviewerUserUuid, bool $isOwnSheet, string $comment): Timesheet
    {
        if (mb_trim($comment) === '') {
            throw new DomainException('Say why the timesheet is being returned.');
        }

        return $this->review($timesheet, Timesheet::STATUS_REJECTED, $reviewerUserUuid, $isOwnSheet, $comment);
    }

    private function review(Timesheet $timesheet, string $status, ?string $reviewerUserUuid, bool $isOwnSheet, ?string $comment): Timesheet
    {
        if ($timesheet->status !== Timesheet::STATUS_SUBMITTED) {
            throw new DomainException('Only a submitted timesheet can be reviewed.');
        }

        if ($isOwnSheet) {
            throw new DomainException('You cannot review your own timesheet.');
        }

        $timesheet->update(['status' => $status, 'reviewed_by' => $reviewerUserUuid, 'reviewed_at' => now(), 'review_comment' => $comment]);

        return $timesheet;
    }
}
