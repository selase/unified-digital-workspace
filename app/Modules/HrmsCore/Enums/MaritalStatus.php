<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Enums;

enum MaritalStatus: string
{
    case Single = 'single';
    case Married = 'married';
    case Divorced = 'divorced';
    case Widowed = 'widowed';
    case Separated = 'separated';

    /**
     * Get the display label for this marital status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Single => 'Single',
            self::Married => 'Married',
            self::Divorced => 'Divorced',
            self::Widowed => 'Widowed',
            self::Separated => 'Separated',
        };
    }
}
