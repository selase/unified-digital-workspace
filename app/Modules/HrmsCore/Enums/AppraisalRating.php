<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Enums;

enum AppraisalRating: int
{
    case Unsatisfactory = 1;
    case BelowExpectations = 2;
    case MeetsExpectations = 3;
    case ExceedsExpectations = 4;
    case Outstanding = 5;

    /**
     * Get the display label for this rating.
     */
    public function label(): string
    {
        return match ($this) {
            self::Unsatisfactory => 'Unsatisfactory',
            self::BelowExpectations => 'Below Expectations',
            self::MeetsExpectations => 'Meets Expectations',
            self::ExceedsExpectations => 'Exceeds Expectations',
            self::Outstanding => 'Outstanding',
        };
    }

    /**
     * Get the short label for this rating.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Unsatisfactory => 'Poor',
            self::BelowExpectations => 'Below',
            self::MeetsExpectations => 'Meets',
            self::ExceedsExpectations => 'Exceeds',
            self::Outstanding => 'Outstanding',
        };
    }

    /**
     * Get the CSS class for styling this rating.
     */
    public function cssClass(): string
    {
        return match ($this) {
            self::Unsatisfactory => 'bg-red-100 text-red-800',
            self::BelowExpectations => 'bg-orange-100 text-orange-800',
            self::MeetsExpectations => 'bg-yellow-100 text-yellow-800',
            self::ExceedsExpectations => 'bg-blue-100 text-blue-800',
            self::Outstanding => 'bg-green-100 text-green-800',
        };
    }

    /**
     * Get the description for this rating.
     */
    public function description(): string
    {
        return match ($this) {
            self::Unsatisfactory => 'Performance consistently fails to meet minimum job requirements.',
            self::BelowExpectations => 'Performance does not fully meet job requirements in some areas.',
            self::MeetsExpectations => 'Performance consistently meets job requirements and expectations.',
            self::ExceedsExpectations => 'Performance frequently exceeds job requirements.',
            self::Outstanding => 'Performance consistently exceeds all job requirements with exceptional results.',
        };
    }

    /**
     * Get all ratings as options for forms.
     *
     * @return array<int, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $rating): array => [$rating->value => $rating->label()])
            ->all();
    }
}
