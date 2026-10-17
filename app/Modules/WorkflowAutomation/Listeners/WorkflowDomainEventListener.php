<?php

declare(strict_types=1);

namespace App\Modules\WorkflowAutomation\Listeners;

use App\Modules\WorkflowAutomation\Events\WorkflowDomainEvent;
use App\Modules\WorkflowAutomation\Services\WorkflowEngineService;
use Illuminate\Contracts\Queue\ShouldQueue;

final class WorkflowDomainEventListener implements ShouldQueue
{
    public function __construct(
        private readonly WorkflowEngineService $engine
    ) {}

    public function handle(WorkflowDomainEvent $event): void
    {
        $this->engine->trigger($event->tenantId, $event->eventName, $event->payload);
    }
}
