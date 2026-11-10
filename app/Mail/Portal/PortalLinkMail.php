<?php

declare(strict_types=1);

namespace App\Mail\Portal;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class PortalLinkMail extends Mailable
{
    public function __construct(
        public readonly string $name,
        public readonly string $organisation,
        public readonly string $url,
        public readonly string $purpose,
        public readonly int $minutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your sign-in link for {$this->organisation}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.portal-link');
    }
}
