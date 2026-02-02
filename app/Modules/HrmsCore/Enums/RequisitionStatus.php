<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Enums;

enum RequisitionStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Open = 'open';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    /**
     * Get the display label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Pending Approval',
            self::Approved => 'Approved',
            self::Open => 'Open',
            self::Closed => 'Closed',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Get the CSS class for styling this status.
     */
    public function cssClass(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-100 text-gray-800',
            self::PendingApproval => 'bg-yellow-100 text-yellow-800',
            self::Approved => 'bg-blue-100 text-blue-800',
            self::Open => 'bg-green-100 text-green-800',
            self::Closed => 'bg-purple-100 text-purple-800',
            self::Cancelled => 'bg-red-100 text-red-800',
        };
    }

    /**
     * Check if this is a final status.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Closed, self::Cancelled], true);
    }

    /**
     * Check if requisition is accepting applications.
     */
    public function isAcceptingApplications(): bool
    {
        return $this === self::Open;
    }
}
