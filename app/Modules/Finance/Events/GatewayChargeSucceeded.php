<?php

declare(strict_types=1);

namespace App\Modules\Finance\Events;

/** Raised when a payment gateway reports a successful charge for a tenant. Other modules decide whether it is theirs. */
final class GatewayChargeSucceeded
{
    public function __construct(public readonly string $tenantId, public readonly string $reference) {}
}
