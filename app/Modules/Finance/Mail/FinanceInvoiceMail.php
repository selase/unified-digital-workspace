<?php

declare(strict_types=1);

namespace App\Modules\Finance\Mail;

use App\Modules\Finance\Models\FinInvoice;
use App\Modules\Finance\Services\FinancePdf;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class FinanceInvoiceMail extends Mailable
{
    public function __construct(
        public readonly FinInvoice $document,
        public readonly string $senderName,
        public readonly string $viewUrl,
        public readonly ?string $replyToAddress = null,
    ) {}

    public function envelope(): Envelope
    {
        $label = $this->document->type === FinInvoice::TYPE_QUOTE ? 'Quote' : 'Invoice';

        return new Envelope(
            subject: "{$label} {$this->document->number} from {$this->senderName}",
            replyTo: $this->replyToAddress ? [$this->replyToAddress] : [],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'finance::emails.invoice');
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        $pdf = app(FinancePdf::class);

        return [Attachment::fromData(fn (): string => $pdf->output($this->document), $pdf->filename($this->document))->withMime('application/pdf')];
    }
}
