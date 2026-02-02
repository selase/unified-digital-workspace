<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Enums;

enum PromotionStatus: string
{
    case Pending = 'pending';
    case AwaitingSupervisorApproval = 'awaiting_supervisor_approval';
    case AwaitingHrApproval = 'awaiting_hr_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * Get the display label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::AwaitingSupervisorApproval => 'Awaiting Supervisor Approval',
            self::AwaitingHrApproval => 'Awaiting HR Approval',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    /**
     * Get the CSS class for styling this status.
     */
    public function cssClass(): string
    {
        return match ($this) {
            self::Pending, self::AwaitingSupervisorApproval => 'bg-yellow-100 text-yellow-800',
            self::AwaitingHrApproval => 'bg-blue-100 text-blue-800',
            self::Approved => 'bg-green-100 text-green-800',
            self::Rejected => 'bg-red-100 text-red-800',
        };
    }

    /**
     * Check if this is a final status.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Approved, self::Rejected], true);
    }
}
