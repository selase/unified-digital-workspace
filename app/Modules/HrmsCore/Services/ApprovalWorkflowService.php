<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Services;

use App\Modules\HrmsCore\Enums\LeaveStatus;
use App\Modules\HrmsCore\Enums\PromotionStatus;

/**
 * Service for managing approval workflows (leave, promotions).
 */
final class ApprovalWorkflowService
{
    /**
     * Check if a leave request can be approved at the supervisor level.
     */
    public function canSupervisorApproveLeave(LeaveStatus $currentStatus): bool
    {
        return $currentStatus === LeaveStatus::Pending;
    }

    /**
     * Check if a leave request can be approved at the HR level.
     */
    public function canHrApproveLeave(LeaveStatus $supervisorStatus): bool
    {
        return $supervisorStatus === LeaveStatus::Verified;
    }

    /**
     * Check if a promotion request can be recommended by supervisor.
     */
    public function canSupervisorRecommendPromotion(PromotionStatus $currentStatus): bool
    {
        return $currentStatus === PromotionStatus::Pending;
    }

    /**
     * Check if a promotion request can be approved by HR.
     */
    public function canHrApprovePromotion(PromotionStatus $supervisorStatus): bool
    {
        return $supervisorStatus === PromotionStatus::AwaitingHrApproval;
    }
}
