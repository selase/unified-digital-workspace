<?php

declare(strict_types=1);

namespace App\Mail\Billing;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class MonthlyInvoiceRunFailed extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, string>  $failures  Tenant name => error message
     */
    public function __construct(
        public string $period,
        public array $failures,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Action needed: invoices were not generated for '.count($this->failures).' tenant(s)');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.billing.invoice_run_failed');
    }
}
