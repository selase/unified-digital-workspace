<?php

declare(strict_types=1);

namespace App\Mail\Incidents;

use App\Modules\IncidentManagement\Models\Incident;
use App\Modules\IncidentManagement\Models\IncidentReminder as IncidentReminderModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class IncidentReminder extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Incident $incident, public IncidentReminderModel $reminder) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Incident Reminder: {$this->incident->reference_code}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.incidents.reminder',
        );
    }
}
