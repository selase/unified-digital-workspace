<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\BillingMessage;
use App\Services\Billing\InvoicePaymentLinkService;
use App\Services\Billing\InvoiceReceiptService;
use App\Services\Tenancy\PdfInvoiceService;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

final class InvoiceBillingNotice extends Mailable
{
    public function __construct(public readonly BillingMessage $billingMessage) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->billingMessage->kind === 'payment_receipt'
            ? 'Payment receipt — '.$this->billingMessage->payload['number'] : $this->billingMessage->payload['subject']);
    }

    public function headers(): Headers
    {
        return new Headers(text: ['X-UDW-System-Transactional' => 'billing']);
    }

    public function content(): Content
    {
        $invoice = $this->billingMessage->invoice;

        return new Content(markdown: 'emails.billing.notice', with: [
            'messageRecord' => $this->billingMessage, 'details' => $this->billingMessage->payload,
            'paymentUrl' => $invoice ? app(InvoicePaymentLinkService::class)->url($invoice) : null,
        ]);
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        $invoice = $this->billingMessage->invoice;
        if ($invoice === null) {
            return [];
        }
        if ($this->billingMessage->kind === 'payment_receipt') {
            return [Attachment::fromData(fn (): string => app(InvoiceReceiptService::class)->output($invoice), 'Receipt-'.$invoice->number.'.pdf')->withMime('application/pdf')];
        }

        return [Attachment::fromData(fn (): string => app(PdfInvoiceService::class)->output($invoice), 'Invoice-'.$invoice->number.'.pdf')->withMime('application/pdf')];
    }
}
