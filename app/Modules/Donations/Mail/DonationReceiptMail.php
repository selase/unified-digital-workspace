<?php

declare(strict_types=1);

namespace App\Modules\Donations\Mail;

use App\Modules\Donations\Models\DonDonation;
use App\Modules\Donations\Services\DonationReceiptPdf;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class DonationReceiptMail extends Mailable
{
    public function __construct(
        public readonly DonDonation $donation,
        public readonly string $senderName,
        public readonly ?string $replyToAddress = null,
        public readonly ?string $stopAutoChargeUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Thank you: receipt {$this->donation->receipt_number} from {$this->senderName}",
            replyTo: $this->replyToAddress ? [$this->replyToAddress] : [],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'donations::emails.receipt');
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        $pdf = app(DonationReceiptPdf::class);

        return [Attachment::fromData(fn (): string => $pdf->output($this->donation), $pdf->filename($this->donation))->withMime('application/pdf')];
    }
}
