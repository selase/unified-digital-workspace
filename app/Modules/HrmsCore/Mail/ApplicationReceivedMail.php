<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Tells an applicant their application arrived, so they are not left wondering whether the form worked. */
final class ApplicationReceivedMail extends Mailable
{
    public function __construct(
        public readonly string $applicantName,
        public readonly string $jobTitle,
        public readonly string $reference,
        public readonly string $organisation,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "We received your application for {$this->jobTitle}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'hrms-core::emails.application-received');
    }
}
