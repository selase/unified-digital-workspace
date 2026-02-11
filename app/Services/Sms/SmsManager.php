<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Models\Tenant;

final class SmsManager
{
    public function __construct(
        private readonly MNotifySmsProvider $mnotify,
        private readonly TwilioSmsProvider $twilio,
    ) {}

    public function send(Tenant $tenant, string $to, string $message): void
    {
        $provider = $this->resolveProvider($tenant);
        $from = $tenant->meta['sms_from'] ?? config('services.sms.from');

        if ($provider === 'twilio') {
            $this->twilio->send($to, $message, $from);

            return;
        }

        $this->mnotify->send($to, $message, $from);
    }

    private function resolveProvider(Tenant $tenant): string
    {
        return (string) ($tenant->meta['sms_provider'] ?? config('services.sms.default', 'mnotify'));
    }
}
