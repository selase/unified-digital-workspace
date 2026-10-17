<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Modules\Store\Models\TenantCreditWallet;
use App\Modules\Store\Services\PlatformCreditService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Log;

final class NotificationDeliveryCreditListener
{
    public function __construct(
        private readonly PlatformCreditService $creditService,
        private readonly TenantContext $tenantContext
    ) {}

    /**
     * Pre-flight balance check: prevent sending if tenant has zero credit balance.
     */
    public function handleSending(NotificationSending $event): bool
    {
        $creditType = $this->resolveCreditTypeForChannel($event->channel);
        if ($creditType === null) {
            return true;
        }

        $tenantId = $this->resolveTenantId($event->notifiable);
        if ($tenantId === null) {
            return true;
        }

        // Only enforce credit quotas if the tenant has a registered wallet for this quota type
        $walletExists = TenantCreditWallet::where('tenant_id', $tenantId)
            ->where('credit_type', $creditType)
            ->exists();

        if ($walletExists && ! $this->creditService->hasBalance($tenantId, $creditType, 1)) {
            Log::warning("Notification blocked: Tenant [{$tenantId}] has 0 [{$creditType}] credits remaining.");

            return false;
        }

        return true;
    }

    /**
     * Post-delivery billing: consume credit strictly upon successful delivery.
     */
    public function handleSent(NotificationSent $event): void
    {
        $creditType = $this->resolveCreditTypeForChannel($event->channel);
        if ($creditType === null) {
            return;
        }

        $tenantId = $this->resolveTenantId($event->notifiable);
        if ($tenantId === null) {
            return;
        }

        $walletExists = TenantCreditWallet::where('tenant_id', $tenantId)
            ->where('credit_type', $creditType)
            ->exists();

        if ($walletExists) {
            $this->creditService->consumeCredit($tenantId, $creditType, 1);
            Log::info("Consumed 1 [{$creditType}] credit for tenant [{$tenantId}] on confirmed delivery.");
        }
    }

    private function resolveCreditTypeForChannel(string $channel): ?string
    {
        return match ($channel) {
            'mail' => 'email',
            'sms' => 'sms',
            default => null,
        };
    }

    private function resolveTenantId(mixed $notifiable): ?string
    {
        if (is_object($notifiable) && isset($notifiable->tenant_id)) {
            return (string) $notifiable->tenant_id;
        }

        $currentTenant = $this->tenantContext->getTenant();
        if ($currentTenant) {
            return (string) $currentTenant->id;
        }

        return null;
    }
}
