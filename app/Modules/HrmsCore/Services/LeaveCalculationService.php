<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Services;

use Carbon\Carbon;

/**
 * Service for leave calculation and validation.
 */
final class LeaveCalculationService
{
    /**
     * Calculate working days between two dates (excluding weekends).
     */
    public function calculateWorkingDays(Carbon $startDate, Carbon $endDate): int
    {
        $workingDays = 0;
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            if (! $current->isWeekend()) {
                $workingDays++;
            }
            $current->addDay();
        }

        return $workingDays;
    }

    /**
     * Calculate outstanding leave days for an employee.
     *
     * @param  int  $entitlement  Annual entitlement
     * @param  int  $usedDays  Days already used
     * @param  int  $carryForward  Days carried from previous year
     */
    public function calculateOutstandingDays(int $entitlement, int $usedDays, int $carryForward = 0): int
    {
        return max(0, $entitlement + $carryForward - $usedDays);
    }
}
