<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Enums;

enum LeaveStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Recalled = 'recalled';
    case NotApproved = 'not_approved';

    /**
     * Get the display label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Verified => 'Verified',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
            self::Recalled => 'Recalled',
            self::NotApproved => 'Not Approved',
        };
    }

    /**
     * Get the CSS class for styling this status.
     */
    public function cssClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-yellow-100 text-yellow-800',
            self::Verified => 'bg-blue-100 text-blue-800',
            self::Approved => 'bg-green-100 text-green-800',
            self::Rejected, self::NotApproved => 'bg-red-100 text-red-800',
            self::Cancelled => 'bg-gray-100 text-gray-800',
            self::Recalled => 'bg-orange-100 text-orange-800',
        };
    }

    /**
     * Check if this is a final status.
     */
    public function isFinal(): bool
    {
        return in_array($this, [
            self::Approved,
            self::Rejected,
            self::Cancelled,
            self::NotApproved,
        ], true);
    }
}
