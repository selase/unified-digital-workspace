<?php

declare(strict_types=1);

namespace App\Listeners\Tenancy;

use App\Enum\UsageMetric;
use App\Models\Tenant;
use App\Models\UsageEvent;
use App\Services\Tenancy\UsageService;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Mail\SentMessage;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Events\NotificationSkipped;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final class MeterTenantMail
{
    public const TENANT_HEADER = 'X-UDW-Tenant-ID';

    private ?string $queuedTenantId = null;

    private bool $queuedMailable = false;

    /** @var array<int, true> */
    private array $notifications = [];

    public function __construct(private readonly UsageService $usageService) {}

    public function handleJobProcessing(JobProcessing $event): void
    {
        $this->reset();
        $payload = $event->job->payload();
        $command = $payload['data']['commandName'] ?? null;
        if (in_array($command, [SendQueuedMailable::class, SendQueuedNotifications::class], true)) {
            $this->queuedTenantId = $this->validTenantId($payload['tenant_id'] ?? null);
            $this->queuedMailable = $command === SendQueuedMailable::class;
        }
    }

    public function handleJobFinished(JobProcessed|JobFailed|JobExceptionOccurred $event): void
    {
        $this->reset();
    }

    public function handleNotificationSending(NotificationSending $event): void
    {
        if ($event->channel === 'mail') {
            $this->notifications[spl_object_id($event->notification)] = true;
        }
    }

    public function handleNotificationFinished(NotificationSent|NotificationFailed|NotificationSkipped $event): void
    {
        if ($event->channel === 'mail') {
            unset($this->notifications[spl_object_id($event->notification)]);
        }
    }

    public function handleMessageSent(MessageSent $event): void
    {
        if ($this->notifications !== [] || isset($event->data['__laravel_notification_id'])) {
            return;
        }

        $message = $event->sent->getSymfonySentMessage()->getOriginalMessage();
        if (! $message instanceof Email) {
            return;
        }

        $headers = iterator_to_array($message->getHeaders()->all(self::TENANT_HEADER), false);
        $tenantId = count($headers) === 1 ? $this->validTenantId($headers[0]->getBodyAsString()) : null;
        if ($headers !== [] && $tenantId === null) {
            return;
        }
        if ($tenantId !== null && $this->queuedTenantId !== null && $tenantId !== $this->queuedTenantId) {
            return;
        }
        $tenantId ??= $this->queuedMailable ? $this->queuedTenantId : null;
        if ($tenantId !== null) {
            $this->record($event->sent, $tenantId, 0);
        }
    }

    /**
     * Called after the existing notification listener attempts its wallet debit.
     * Only that confirmed debit, rather than wallet existence, marks a unit prepaid.
     */
    public function recordNotificationDelivery(NotificationSent $event, string $debitedTenantId, bool $prepaid): void
    {
        if ($event->channel !== 'mail' || ! $event->response instanceof SentMessage) {
            return;
        }
        $tenantId = $this->validTenantId($event->notifiable->tenant_id ?? null) ?? $this->queuedTenantId;
        if ($tenantId === null || $tenantId !== $debitedTenantId) {
            return;
        }
        if ($this->queuedTenantId !== null && $tenantId !== $this->queuedTenantId) {
            return;
        }

        $this->record($event->response, $tenantId, $prepaid ? 1 : 0);
    }

    private function record(SentMessage $sent, string $tenantId, int $prepaidUnits): void
    {
        $tenant = Tenant::query()->find($tenantId);
        if ($tenant === null) {
            return;
        }
        $recipients = collect($sent->getSymfonySentMessage()->getEnvelope()->getRecipients())
            ->map(fn (Address $address): string => mb_strtolower($address->getAddress()))
            ->unique()->count();
        $prepaidUnits = min($prepaidUnits, $recipients);
        $quantities = ['prepaid' => $prepaidUnits, 'metered' => $recipients - $prepaidUnits];
        $time = now();

        $tenant->getConnection()->transaction(function () use ($tenant, $sent, $quantities, $time): void {
            foreach ($quantities as $mode => $quantity) {
                if ($quantity === 0) {
                    continue;
                }
                $dimensions = ['billing_mode' => $mode];
                UsageEvent::query()->create([
                    'tenant_id' => $tenant->id,
                    'occurred_at' => $time,
                    'type' => UsageMetric::EMAIL_COUNT,
                    'key' => hash('sha256', $sent->getSymfonySentMessage()->getMessageId()),
                    'quantity' => $quantity,
                    'unit' => 'count',
                    'meta' => $dimensions,
                ]);
                $this->usageService->updateRollup($tenant, 'minute', $time, UsageMetric::EMAIL_COUNT, $quantity, $dimensions);
            }
        });
    }

    private function validTenantId(mixed $id): ?string
    {
        return is_string($id) && Str::isUuid($id) ? $id : null;
    }

    private function reset(): void
    {
        $this->queuedTenantId = null;
        $this->queuedMailable = false;
        $this->notifications = [];
    }
}
