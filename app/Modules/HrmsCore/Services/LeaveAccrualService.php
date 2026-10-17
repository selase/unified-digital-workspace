<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Services;

use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Models\Leave\LeaveBalance;
use App\Modules\HrmsCore\Models\Leave\LeaveCategory;

/**
 * Service for leave accrual, rollover, and annual entitlement management.
 */
final class LeaveAccrualService
{
    /**
     * Calculate prorated leave entitlement for employees joining mid-year.
     *
     * @param  float  $annualDefaultDays  Full annual entitlement (e.g. 24 days)
     * @param  \Carbon\CarbonInterface  $joinedAt  Employee joining date
     * @param  int  $year  Leave entitlement year
     * @return float Prorated entitlement rounded to 1 decimal place
     */
    public function calculateProratedEntitlement(
        float $annualDefaultDays,
        \Carbon\CarbonInterface $joinedAt,
        int $year
    ): float {
        $joinYear = (int) $joinedAt->year;

        if ($joinYear < $year) {
            return $annualDefaultDays;
        }

        if ($joinYear > $year) {
            return 0.0;
        }

        // Mid-year joiner: calculate remaining months including joining month
        $remainingMonths = max(1, 12 - (int) $joinedAt->month + 1);

        return round(($annualDefaultDays / 12) * $remainingMonths, 1);
    }

    /**
     * Rollover leave balances from $fromYear to $toYear for a tenant or across active tenants.
     * Safe to re-run: never resets used_days or pending_days on an existing balance.
     * Respects per-category carry-forward cap if defined, falling back to $maxCarryForward.
     *
     * @param  int  $fromYear  The closing leave year (e.g. 2024)
     * @param  int  $toYear  The new leave year (e.g. 2025)
     * @param  string|null  $tenantId  Optional tenant UUID to scope the rollover
     * @param  int  $maxCarryForward  Default maximum days eligible for carry-forward (default: 10)
     * @return int Number of employee category balances processed for $toYear
     */
    public function rolloverYear(
        int $fromYear,
        int $toYear,
        ?string $tenantId = null,
        int $maxCarryForward = 10
    ): int {
        $query = LeaveBalance::query()->where('year', $fromYear);

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $oldBalances = $query->with(['employee', 'leaveCategory'])->get();
        $processedCount = 0;

        foreach ($oldBalances as $oldBalance) {
            /** @var Employee|null $employee */
            $employee = $oldBalance->employee;

            // Only rollover for active employees
            if (! $employee || ! $employee->is_active) {
                continue;
            }

            /** @var LeaveCategory|null $category */
            $category = $oldBalance->leaveCategory;

            // Unused days from closing year
            $available = (float) ($oldBalance->entitled_days + $oldBalance->carried_forward_days);
            $unusedDays = max(0.0, (float) ($available - $oldBalance->used_days));

            // Per-category cap if defined, otherwise default maxCarryForward
            $categoryCap = $category?->max_carry_forward_days !== null
                ? (float) $category->max_carry_forward_days
                : (float) $maxCarryForward;

            $carriedForward = min($unusedDays, $categoryCap);

            // Standard annual entitlement for new year
            $newEntitlement = $category !== null ? (float) $category->default_days : (float) $oldBalance->entitled_days;

            // Idempotent rollover: Never reset used_days or pending_days on an existing balance
            $existing = LeaveBalance::query()->where([
                'tenant_id' => $oldBalance->tenant_id,
                'employee_id' => $oldBalance->employee_id,
                'leave_category_id' => $oldBalance->leave_category_id,
                'year' => $toYear,
            ])->first();

            if ($existing !== null) {
                $existing->update([
                    'carried_forward_days' => $carriedForward,
                ]);
            } else {
                LeaveBalance::create([
                    'tenant_id' => $oldBalance->tenant_id,
                    'employee_id' => $oldBalance->employee_id,
                    'leave_category_id' => $oldBalance->leave_category_id,
                    'year' => $toYear,
                    'entitled_days' => $newEntitlement,
                    'carried_forward_days' => $carriedForward,
                    'used_days' => 0.0,
                    'pending_days' => 0.0,
                ]);
            }

            $processedCount++;
        }

        return $processedCount;
    }
}
