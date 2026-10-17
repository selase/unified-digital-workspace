<?php

declare(strict_types=1);

namespace App\Modules\WorkflowAutomation\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class WorkflowDomainEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $eventName,
        public readonly array $payload,
    ) {}
}
