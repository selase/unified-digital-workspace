<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Listeners;

use App\Modules\Finance\Events\GatewayChargeSucceeded;
use App\Modules\Memberships\Services\MembershipService;

final class ApplyMembershipPayments
{
    public function __construct(private readonly MembershipService $memberships) {}

    public function handle(GatewayChargeSucceeded $event): void
    {
        $this->memberships->applyPayments($event->tenantId);
    }
}
