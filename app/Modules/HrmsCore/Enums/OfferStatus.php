<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Enums;

enum OfferStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
    case Expired = 'expired';

    /**
     * Get the display label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Pending Approval',
            self::Sent => 'Sent',
            self::Accepted => 'Accepted',
            self::Rejected => 'Rejected',
            self::Withdrawn => 'Withdrawn',
            self::Expired => 'Expired',
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
            self::Sent => 'bg-blue-100 text-blue-800',
            self::Accepted => 'bg-green-100 text-green-800',
            self::Rejected => 'bg-red-100 text-red-800',
            self::Withdrawn => 'bg-orange-100 text-orange-800',
            self::Expired => 'bg-purple-100 text-purple-800',
        };
    }

    /**
     * Check if this is a final status.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Accepted, self::Rejected, self::Withdrawn, self::Expired], true);
    }

    /**
     * Check if offer can be modified.
     */
    public function canModify(): bool
    {
        return in_array($this, [self::Draft, self::PendingApproval], true);
    }

    /**
     * Check if candidate can respond.
     */
    public function awaitingResponse(): bool
    {
        return $this === self::Sent;
    }
}
