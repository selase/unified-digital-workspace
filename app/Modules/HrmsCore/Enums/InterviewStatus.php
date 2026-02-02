<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Enums;

enum InterviewStatus: string
{
    case Scheduled = 'scheduled';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    /**
     * Get the display label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::Confirmed => 'Confirmed',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::NoShow => 'No Show',
        };
    }

    /**
     * Get the CSS class for styling this status.
     */
    public function cssClass(): string
    {
        return match ($this) {
            self::Scheduled => 'bg-yellow-100 text-yellow-800',
            self::Confirmed => 'bg-blue-100 text-blue-800',
            self::Completed => 'bg-green-100 text-green-800',
            self::Cancelled => 'bg-red-100 text-red-800',
            self::NoShow => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Check if this is a final status.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled, self::NoShow], true);
    }

    /**
     * Check if interview can be rescheduled.
     */
    public function canReschedule(): bool
    {
        return in_array($this, [self::Scheduled, self::Confirmed], true);
    }
}
