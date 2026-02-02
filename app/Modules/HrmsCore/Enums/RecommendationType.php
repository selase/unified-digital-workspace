<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Enums;

enum RecommendationType: string
{
    case Promotion = 'promotion';
    case Training = 'training';
    case Recognition = 'recognition';
    case SalaryIncrease = 'salary_increase';
    case PerformanceImprovement = 'pip';
    case Termination = 'termination';
    case Transfer = 'transfer';
    case NoAction = 'no_action';

    /**
     * Get the display label for this type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Promotion => 'Promotion',
            self::Training => 'Training & Development',
            self::Recognition => 'Recognition & Award',
            self::SalaryIncrease => 'Salary Increase',
            self::PerformanceImprovement => 'Performance Improvement Plan',
            self::Termination => 'Termination',
            self::Transfer => 'Transfer/Reassignment',
            self::NoAction => 'No Action Required',
        };
    }

    /**
     * Get the CSS class for styling this type.
     */
    public function cssClass(): string
    {
        return match ($this) {
            self::Promotion => 'bg-green-100 text-green-800',
            self::Training => 'bg-blue-100 text-blue-800',
            self::Recognition => 'bg-purple-100 text-purple-800',
            self::SalaryIncrease => 'bg-emerald-100 text-emerald-800',
            self::PerformanceImprovement => 'bg-orange-100 text-orange-800',
            self::Termination => 'bg-red-100 text-red-800',
            self::Transfer => 'bg-yellow-100 text-yellow-800',
            self::NoAction => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Check if this is a positive recommendation.
     */
    public function isPositive(): bool
    {
        return in_array($this, [
            self::Promotion,
            self::Recognition,
            self::SalaryIncrease,
        ], true);
    }

    /**
     * Check if this is a corrective action.
     */
    public function isCorrective(): bool
    {
        return in_array($this, [
            self::PerformanceImprovement,
            self::Termination,
        ], true);
    }

    /**
     * Get all types as options for forms.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }
}
