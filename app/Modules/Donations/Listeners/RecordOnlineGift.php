<?php

declare(strict_types=1);

namespace App\Modules\Donations\Listeners;

use App\Modules\Donations\Services\OnlineGivingService;
use App\Modules\Finance\Events\GatewayChargeSucceeded;

final class RecordOnlineGift
{
    public function __construct(private readonly OnlineGivingService $giving) {}

    public function handle(GatewayChargeSucceeded $event): void
    {
        $this->giving->recordVerified($event->tenantId, $event->reference);
    }
}
