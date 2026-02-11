<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Contracts\Sms\SmsProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class MNotifySmsProvider implements SmsProvider
{
    public function send(string $to, string $message, ?string $from = null): void
    {
        $endpoint = config('services.mnotify.endpoint', 'https://apps.mnotify.net/smsapi');
        $apiKey = config('services.mnotify.key');
        $senderId = $from ?: config('services.mnotify.sender_id');

        if (! $apiKey || ! $senderId) {
            Log::warning('mNotify SMS config missing. Skipping SMS send.', [
                'to' => $to,
            ]);

            return;
        }

        Http::asForm()->post($endpoint, [
            'key' => $apiKey,
            'to' => $to,
            'msg' => $message,
            'sender_id' => $senderId,
        ]);
    }
}
