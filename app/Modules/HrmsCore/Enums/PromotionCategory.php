<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Enums;

enum PromotionCategory: string
{
    case Promotion = 'promotion';
    case Regrading = 'regrading';
    case Upgrade = 'upgrade';
    case Conversion = 'conversion';

    /**
     * Get the display label for this category.
     */
    public function label(): string
    {
        return match ($this) {
            self::Promotion => 'Promotion',
            self::Regrading => 'Re-grading',
            self::Upgrade => 'Upgrade',
            self::Conversion => 'Conversion',
        };
    }

    /**
     * Get the description for this category.
     */
    public function description(): string
    {
        return match ($this) {
            self::Promotion => 'Advancement to a higher position based on performance and tenure',
            self::Regrading => 'Adjustment of grade level based on role reassessment',
            self::Upgrade => 'Movement to a higher salary step within the same grade',
            self::Conversion => 'Change of employment category or contract type',
        };
    }
}
