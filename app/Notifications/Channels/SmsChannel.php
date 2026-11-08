<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Models\Tenant;
use App\Services\Sms\SmsManager;
use App\Services\Tenancy\TenantContext;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Sends a notification's toSms() text to the notifiable's phone. The existing
 * credit listener sees this channel as "sms", so prepaid balances apply.
 */
final class SmsChannel
{
    public function __construct(private readonly SmsManager $sms) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $phone = method_exists($notifiable, 'routeNotificationFor') ? $notifiable->routeNotificationFor('sms', $notification) : null;
        $tenantId = $notifiable->tenant_id ?? app(TenantContext::class)->getTenant()?->id;
        $tenant = $tenantId !== null ? Tenant::query()->find($tenantId) : null;

        if (! is_string($phone) || $phone === '' || $tenant === null) {
            Log::warning('SMS alert skipped: no phone number or organisation for the recipient.');

            return;
        }

        $this->sms->send($tenant, $phone, $notification->toSms($notifiable));
    }
}
