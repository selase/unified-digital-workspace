<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Billing\BillingCommunicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SendBillingMessage implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 600;

    public int $timeout = 120;

    public function __construct(public readonly string $messageId) {}

    public function uniqueId(): string
    {
        return $this->messageId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(BillingCommunicationService $communications): void
    {
        $communications->deliver($this->messageId);
    }
}
