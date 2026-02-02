<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Services;

/**
 * Service for employee-related business logic.
 */
final class EmployeeService
{
    /**
     * Generate a unique employee staff ID.
     */
    public function generateStaffId(string $prefix = 'EMP'): string
    {
        return $prefix.'-'.mb_strtoupper(mb_substr(md5((string) microtime(true)), 0, 8));
    }
}
