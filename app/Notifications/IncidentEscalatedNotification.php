<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Modules\IncidentManagement\Models\Incident;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class IncidentEscalatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Incident $incident,
        public readonly ?string $reason = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (! empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("[Incident Escalation] {$this->incident->reference_code} - {$this->incident->title}")
            ->greeting('Priority Incident Escalation')
            ->line("Incident {$this->incident->reference_code} ({$this->incident->title}) has been escalated and requires your prompt attention.");

        if (! empty($this->reason)) {
            $mail->line("Escalation Reason: {$this->reason}");
        }

        return $mail->action('Review Incident', route('incident-management.incidents.show', $this->incident));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $message = "Incident {$this->incident->reference_code} ({$this->incident->title}) has been escalated.";
        if (! empty($this->reason)) {
            $message .= " Reason: {$this->reason}";
        }

        return [
            'title' => "Incident Escalated: {$this->incident->reference_code}",
            'message' => $message,
            'incident_id' => $this->incident->id,
            'reference_code' => $this->incident->reference_code,
            'reason' => $this->reason,
            'action_url' => route('incident-management.incidents.show', $this->incident),
        ];
    }
}
