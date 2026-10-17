<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Services;

use App\Modules\HrmsCore\Models\Leave\Holiday;

/**
 * Service for leave calculation, holiday exclusion, and balance validation.
 */
final class LeaveCalculationService
{
    /**
     * Calculate working days between two dates (excluding weekends and active tenant holidays).
     * Supports optional half-day deduction for single-day requests.
     */
    public function calculateWorkingDays(
        \Carbon\CarbonInterface $startDate,
        \Carbon\CarbonInterface $endDate,
        ?string $tenantId = null,
        bool $isHalfDay = false
    ): float {
        $daysByYear = $this->calculateWorkingDaysByYear($startDate, $endDate, $tenantId, $isHalfDay);

        return array_sum($daysByYear);
    }

    /**
     * Calculate working days grouped by calendar year (e.g. for leave requests spanning 31 December).
     *
     * @return array<int, float> Mapping of year => working days in that year
     */
    public function calculateWorkingDaysByYear(
        \Carbon\CarbonInterface $startDate,
        \Carbon\CarbonInterface $endDate,
        ?string $tenantId = null,
        bool $isHalfDay = false
    ): array {
        $start = $startDate->copy()->startOfDay();
        $end = $endDate->copy()->startOfDay();

        if ($start->gt($end)) {
            return [];
        }

        // Query active holidays for tenant
        $holidaysQuery = Holiday::query()->where('is_active', true);
        if ($tenantId !== null) {
            $holidaysQuery->where('tenant_id', $tenantId);
        }
        $holidays = $holidaysQuery->get();

        $daysByYear = [];
        $current = $start->copy();

        while ($current->lte($end)) {
            $year = (int) $current->year;
            if (! isset($daysByYear[$year])) {
                $daysByYear[$year] = 0.0;
            }

            // Exclude Saturday and Sunday
            if (! $current->isWeekend()) {
                // Check if current date matches any active holiday
                $isHoliday = false;
                foreach ($holidays as $holiday) {
                    if ($holiday->isOnDate($current)) {
                        $isHoliday = true;
                        break;
                    }
                }

                if (! $isHoliday) {
                    if ($isHalfDay && $start->isSameDay($end)) {
                        $daysByYear[$year] += 0.5;
                    } else {
                        $daysByYear[$year] += 1.0;
                    }
                }
            }
            $current->addDay();
        }

        return $daysByYear;
    }

    /**
     * Calculate outstanding leave days for an employee.
     *
     * @param  float  $entitlement  Annual entitlement
     * @param  float  $usedDays  Days already used
     * @param  float  $carryForward  Days carried from previous year
     * @param  float  $pendingDays  Days in pending approval requests
     */
    public function calculateOutstandingDays(
        float $entitlement,
        float $usedDays,
        float $carryForward = 0.0,
        float $pendingDays = 0.0
    ): float {
        return max(0.0, (float) ($entitlement + $carryForward - $usedDays - $pendingDays));
    }
}
