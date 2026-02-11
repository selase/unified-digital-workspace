<?php

declare(strict_types=1);

namespace App\Contracts\Sms;

interface SmsProvider
{
    public function send(string $to, string $message, ?string $from = null): void;
}
