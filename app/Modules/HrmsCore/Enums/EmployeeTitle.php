<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Enums;

enum EmployeeTitle: string
{
    case Mr = 'Mr.';
    case Mrs = 'Mrs.';
    case Ms = 'Ms.';
    case Miss = 'Miss';
    case Dr = 'Dr.';
    case Prof = 'Prof.';
    case Rev = 'Rev.';
    case Hon = 'Hon.';

    /**
     * Get the display label for this title.
     */
    public function label(): string
    {
        return $this->value;
    }
}
