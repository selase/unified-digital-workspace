<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Enums;

enum Gender: string
{
    case Male = 'male';
    case Female = 'female';
    case Other = 'other';
    case PreferNotToSay = 'prefer_not_to_say';

    /**
     * Get the display label for this gender.
     */
    public function label(): string
    {
        return match ($this) {
            self::Male => 'Male',
            self::Female => 'Female',
            self::Other => 'Other',
            self::PreferNotToSay => 'Prefer not to say',
        };
    }
}
