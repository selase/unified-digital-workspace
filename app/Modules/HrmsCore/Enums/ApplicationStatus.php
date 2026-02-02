<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Enums;

enum ApplicationStatus: string
{
    case Submitted = 'submitted';
    case Screening = 'screening';
    case Shortlisted = 'shortlisted';
    case Interview = 'interview';
    case Assessment = 'assessment';
    case Offer = 'offer';
    case Hired = 'hired';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    /**
     * Get the display label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::Screening => 'Screening',
            self::Shortlisted => 'Shortlisted',
            self::Interview => 'Interview',
            self::Assessment => 'Assessment',
            self::Offer => 'Offer',
            self::Hired => 'Hired',
            self::Rejected => 'Rejected',
            self::Withdrawn => 'Withdrawn',
        };
    }

    /**
     * Get the CSS class for styling this status.
     */
    public function cssClass(): string
    {
        return match ($this) {
            self::Submitted => 'bg-gray-100 text-gray-800',
            self::Screening => 'bg-yellow-100 text-yellow-800',
            self::Shortlisted => 'bg-blue-100 text-blue-800',
            self::Interview => 'bg-indigo-100 text-indigo-800',
            self::Assessment => 'bg-purple-100 text-purple-800',
            self::Offer => 'bg-teal-100 text-teal-800',
            self::Hired => 'bg-green-100 text-green-800',
            self::Rejected => 'bg-red-100 text-red-800',
            self::Withdrawn => 'bg-orange-100 text-orange-800',
        };
    }

    /**
     * Check if this is a final status.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Hired, self::Rejected, self::Withdrawn], true);
    }

    /**
     * Check if this is a positive outcome.
     */
    public function isPositive(): bool
    {
        return $this === self::Hired;
    }

    /**
     * Check if application is in active pipeline.
     */
    public function isActive(): bool
    {
        return ! $this->isFinal();
    }
}
