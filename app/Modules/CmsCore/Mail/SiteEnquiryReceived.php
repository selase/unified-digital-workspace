<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Mail;

use App\Modules\CmsCore\Support\EnquiryType;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Notifies a site owner that a visitor used one of the public enquiry forms.
 * Replying to the email answers the visitor directly.
 */
final class SiteEnquiryReceived extends Mailable
{
    public function __construct(
        public EnquiryType $type,
        public string $siteName,
        public string $visitorName,
        public string $visitorEmail,
        public ?string $phone,
        public ?string $organisation,
        public string $enquiryMessage,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->visitorEmail, $this->singleLine($this->visitorName))],
            subject: sprintf('[%s] %s enquiry from %s', $this->singleLine($this->siteName), $this->type->label(), $this->singleLine($this->visitorName)),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'cms-core::emails.enquiry');
    }

    private function singleLine(string $value): string
    {
        return mb_trim((string) preg_replace('/\s+/', ' ', $value));
    }
}
