<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Invoice;

final class InvoicePaymentLinkService
{
    public function url(Invoice $invoice): string
    {
        return $this->signedUrl($invoice, 'billing.payment.show');
    }

    public function signedUrl(Invoice $invoice, string $route): string
    {
        $generator = clone app('url');
        $root = mb_rtrim((string) config('app.url'), '/');
        $generator->forceRootUrl($root);
        $generator->forceScheme(parse_url($root, PHP_URL_SCHEME) ?: 'https');

        return $generator->temporarySignedRoute($route, now()->addDays(30), ['invoice' => $invoice->id]);
    }

    public function callbackUrl(string $provider): string
    {
        $generator = clone app('url');
        $root = mb_rtrim((string) config('app.url'), '/');
        $generator->forceRootUrl($root);
        $generator->forceScheme(parse_url($root, PHP_URL_SCHEME) ?: 'https');

        return $generator->route('billing.payment.callback', ['provider' => $provider]);
    }
}
