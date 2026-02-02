<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Enums;

enum AppraisalStatus: string
{
    case Draft = 'draft';
    case SelfAssessment = 'self_assessment';
    case SupervisorReview = 'supervisor_review';
    case HodReview = 'hod_review';
    case HrReview = 'hr_review';
    case Complete = 'complete';
    case Cancelled = 'cancelled';

    /**
     * Get the display label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::SelfAssessment => 'Self Assessment',
            self::SupervisorReview => 'Supervisor Review',
            self::HodReview => 'HOD Review',
            self::HrReview => 'HR Review',
            self::Complete => 'Complete',
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
            self::SelfAssessment => 'bg-blue-100 text-blue-800',
            self::SupervisorReview => 'bg-yellow-100 text-yellow-800',
            self::HodReview => 'bg-orange-100 text-orange-800',
            self::HrReview => 'bg-purple-100 text-purple-800',
            self::Complete => 'bg-green-100 text-green-800',
            self::Cancelled => 'bg-red-100 text-red-800',
        };
    }

    /**
     * Check if this is a final status.
     */
    public function isFinal(): bool
    {
        return in_array($this, [
            self::Complete,
            self::Cancelled,
        ], true);
    }

    /**
     * Get the next status in the workflow.
     */
    public function nextStatus(): ?self
    {
        return match ($this) {
            self::Draft => self::SelfAssessment,
            self::SelfAssessment => self::SupervisorReview,
            self::SupervisorReview => self::HodReview,
            self::HodReview => self::HrReview,
            self::HrReview => self::Complete,
            self::Complete, self::Cancelled => null,
        };
    }

    /**
     * Check if a transition to the given status is allowed.
     */
    public function canTransitionTo(self $status): bool
    {
        // Can always cancel
        if ($status === self::Cancelled) {
            return ! $this->isFinal();
        }

        // Normal workflow progression
        return $this->nextStatus() === $status;
    }
}
