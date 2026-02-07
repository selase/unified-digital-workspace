<?php

declare(strict_types=1);

namespace App\Mail\Incidents;

use App\Modules\IncidentManagement\Models\Incident;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class IncidentSlaBreached extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Incident $incident) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Incident SLA Breached: {$this->incident->reference_code}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.incidents.sla-breached',
        );
    }
}
