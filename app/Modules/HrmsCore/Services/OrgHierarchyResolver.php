<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Services;

use App\Modules\HrmsCore\Enums\LeaveStatus;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Models\Leave\LeaveRequest;
use App\Modules\HrmsCore\Models\Organization\Department;
use App\Modules\HrmsCore\Models\Organization\Directorate;
use App\Modules\HrmsCore\Models\Organization\Unit;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class OrgHierarchyResolver
{
    /**
     * Resolve all organizational nodes (Units, Departments, Directorates) an employee belongs to,
     * including inherited parent Departments and Directorates from their Units/Departments.
     *
     * @return array{
     *     units: Collection<int, Unit>,
     *     departments: Collection<int, Department>,
     *     directorates: Collection<int, Directorate>
     * }
     */
    public function resolveOrgNodesForEmployee(Employee $employee): array
    {
        $units = $employee->units()
            ->where('hrms_units.is_active', true)
            ->with(['department.directorate', 'directorate', 'head', 'deputy'])
            ->get();

        $directDepartments = $employee->departments()
            ->where('hrms_departments.is_active', true)
            ->with(['directorate', 'head', 'deputy'])
            ->get();

        $directDirectorates = $employee->directorates()
            ->where('hrms_directorates.is_active', true)
            ->with(['head', 'deputy'])
            ->get();

        /** @var Collection<int, Department> $departments */
        $departments = collect();
        foreach ($directDepartments as $dept) {
            $departments->put($dept->id, $dept);
        }
        foreach ($units as $unit) {
            if ($unit->department && $unit->department->is_active && ! $departments->has($unit->department->id)) {
                $unit->department->loadMissing(['directorate', 'head', 'deputy']);
                $departments->put($unit->department->id, $unit->department);
            }
        }

        /** @var Collection<int, Directorate> $directorates */
        $directorates = collect();
        foreach ($directDirectorates as $dir) {
            $directorates->put($dir->id, $dir);
        }
        foreach ($units as $unit) {
            if ($unit->directorate && $unit->directorate->is_active && ! $directorates->has($unit->directorate->id)) {
                $unit->directorate->loadMissing(['head', 'deputy']);
                $directorates->put($unit->directorate->id, $unit->directorate);
            }
        }
        foreach ($departments as $dept) {
            if ($dept->directorate && $dept->directorate->is_active && ! $directorates->has($dept->directorate->id)) {
                $dept->directorate->loadMissing(['head', 'deputy']);
                $directorates->put($dept->directorate->id, $dept->directorate);
            }
        }

        return [
            'units' => $units->values(),
            'departments' => $departments->values(),
            'directorates' => $directorates->values(),
        ];
    }

    /**
     * Resolve the ordered escalation chain for an employee from lowest tier (Unit)
     * to highest tier (Directorate), automatically failing over from Head to Deputy
     * if the Head is inactive or on approved leave at $atDate.
     *
     * @return array<int, array{level: string, org_id: int, org_name: string, role: string, employee: Employee}>
     */
    public function resolveEscalationChain(Employee $employee, ?CarbonInterface $atDate = null): array
    {
        $date = $atDate ?? now();
        $nodes = $this->resolveOrgNodesForEmployee($employee);

        $chain = [];
        $visitedEmployeeIds = [$employee->id => true];

        // 1. Unit Tier
        foreach ($nodes['units'] as $unit) {
            $leader = $this->resolveEffectiveNodeLeader(
                $unit->head,
                $unit->deputy,
                $visitedEmployeeIds,
                $date
            );
            if ($leader !== null) {
                $visitedEmployeeIds[$leader['employee']->id] = true;
                $chain[] = [
                    'level' => 'unit',
                    'org_id' => $unit->id,
                    'org_name' => $unit->name,
                    'role' => $leader['role'],
                    'employee' => $leader['employee'],
                ];
            }
        }

        // 2. Department Tier
        foreach ($nodes['departments'] as $department) {
            $leader = $this->resolveEffectiveNodeLeader(
                $department->head,
                $department->deputy,
                $visitedEmployeeIds,
                $date
            );
            if ($leader !== null) {
                $visitedEmployeeIds[$leader['employee']->id] = true;
                $chain[] = [
                    'level' => 'department',
                    'org_id' => $department->id,
                    'org_name' => $department->name,
                    'role' => $leader['role'],
                    'employee' => $leader['employee'],
                ];
            }
        }

        // 3. Directorate Tier
        foreach ($nodes['directorates'] as $directorate) {
            $leader = $this->resolveEffectiveNodeLeader(
                $directorate->head,
                $directorate->deputy,
                $visitedEmployeeIds,
                $date
            );
            if ($leader !== null) {
                $visitedEmployeeIds[$leader['employee']->id] = true;
                $chain[] = [
                    'level' => 'directorate',
                    'org_id' => $directorate->id,
                    'org_name' => $directorate->name,
                    'role' => $leader['role'],
                    'employee' => $leader['employee'],
                ];
            }
        }

        return $chain;
    }

    /**
     * Resolve the Supervisor (first-line recommender) and HOD (final approver) for a LeaveRequest.
     *
     * @return array{
     *     supervisor: Employee|null,
     *     hod: Employee|null,
     *     chain: array<int, array{level: string, org_id: int, org_name: string, role: string, employee: Employee}>
     * }
     */
    public function resolveApproversForLeave(Employee $employee, ?CarbonInterface $atDate = null): array
    {
        $chain = $this->resolveEscalationChain($employee, $atDate);

        $supervisor = $chain[0]['employee'] ?? null;
        $hod = $chain[1]['employee'] ?? $supervisor;

        return [
            'supervisor' => $supervisor,
            'hod' => $hod,
            'chain' => $chain,
        ];
    }

    /**
     * Resolve the immediate next escalation target for an employee (e.g. for SLA breach escalation).
     */
    public function resolveEscalationTarget(Employee $employee, ?CarbonInterface $atDate = null): ?Employee
    {
        $chain = $this->resolveEscalationChain($employee, $atDate);

        return $chain[0]['employee'] ?? null;
    }

    /**
     * Determine whether an employee is currently on an active, non-recalled approved leave.
     */
    public function isEmployeeOnActiveLeave(Employee $employee, ?CarbonInterface $atDate = null): bool
    {
        $dateString = ($atDate ?? now())->toDateString();

        return LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', LeaveStatus::Approved)
            ->where('is_recalled', false)
            ->where(function ($query) use ($dateString): void {
                $query->whereRaw('COALESCE(approved_start_date, proposed_start_date) <= ?', [$dateString])
                    ->whereRaw('COALESCE(approved_end_date, proposed_end_date) >= ?', [$dateString]);
            })
            ->exists();
    }

    /**
     * Pick the Head if available and not in $visitedEmployeeIds and not on active leave;
     * otherwise fail over to the Deputy.
     *
     * @param  array<int, bool>  $visitedEmployeeIds
     * @return array{role: string, employee: Employee}|null
     */
    private function resolveEffectiveNodeLeader(
        ?Employee $head,
        ?Employee $deputy,
        array $visitedEmployeeIds,
        CarbonInterface $atDate
    ): ?array {
        if (
            $head !== null
            && $head->is_active
            && ! isset($visitedEmployeeIds[$head->id])
            && ! $this->isEmployeeOnActiveLeave($head, $atDate)
        ) {
            return [
                'role' => 'head',
                'employee' => $head,
            ];
        }

        if (
            $deputy !== null
            && $deputy->is_active
            && ! isset($visitedEmployeeIds[$deputy->id])
            && ! $this->isEmployeeOnActiveLeave($deputy, $atDate)
        ) {
            return [
                'role' => 'deputy',
                'employee' => $deputy,
            ];
        }

        return null;
    }
}
