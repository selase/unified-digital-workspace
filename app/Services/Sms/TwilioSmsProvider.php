<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Contracts\Sms\SmsProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class TwilioSmsProvider implements SmsProvider
{
    public function send(string $to, string $message, ?string $from = null): void
    {
        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');
        $sender = $from ?: config('services.twilio.from');
        $endpoint = config('services.twilio.endpoint', 'https://api.twilio.com/2010-04-01');

        if (! $sid || ! $token || ! $sender) {
            Log::warning('Twilio SMS config missing. Skipping SMS send.', [
                'to' => $to,
            ]);

            return;
        }

        Http::withBasicAuth($sid, $token)
            ->asForm()
            ->post(rtrim($endpoint, '/')."/Accounts/{$sid}/Messages.json", [
                'To' => $to,
                'From' => $sender,
                'Body' => $message,
            ]);
    }
}
