<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\InvoicePaymentReceived;
use App\Services\Billing\BillingCommunicationService;

final class QueueInvoiceReceipt
{
    public function handle(InvoicePaymentReceived $event): void
    {
        app(BillingCommunicationService::class)->receipt($event->invoice);
    }
}
