<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Enums;

enum AppraisalCycle: string
{
    case Annual = 'annual';
    case Midyear = 'midyear';
    case Quarterly = 'quarterly';
    case Probation = 'probation';

    /**
     * Get the display label for this cycle.
     */
    public function label(): string
    {
        return match ($this) {
            self::Annual => 'Annual Review',
            self::Midyear => 'Mid-Year Review',
            self::Quarterly => 'Quarterly Review',
            self::Probation => 'Probation Review',
        };
    }

    /**
     * Get the CSS class for styling this cycle.
     */
    public function cssClass(): string
    {
        return match ($this) {
            self::Annual => 'bg-blue-100 text-blue-800',
            self::Midyear => 'bg-purple-100 text-purple-800',
            self::Quarterly => 'bg-green-100 text-green-800',
            self::Probation => 'bg-orange-100 text-orange-800',
        };
    }

    /**
     * Get the typical duration in months.
     */
    public function durationMonths(): int
    {
        return match ($this) {
            self::Annual => 12,
            self::Midyear => 6,
            self::Quarterly => 3,
            self::Probation => 3, // Typically 3 months, can vary
        };
    }

    /**
     * Get all cycles as options for forms.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $cycle): array => [$cycle->value => $cycle->label()])
            ->all();
    }
}
