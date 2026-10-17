<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class InstitutionalWorkflowAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $title,
        public readonly string $message,
        public readonly int $workflowRuleId,
        public readonly string $actionUrl = '',
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
            ->subject("[Workflow Alert] {$this->title}")
            ->greeting('Automated Workflow Notification')
            ->line($this->message);

        if (! empty($this->actionUrl)) {
            $mail->action('View Details', $this->actionUrl);
        }

        return $mail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'workflow_rule_id' => $this->workflowRuleId,
            'action_url' => $this->actionUrl,
        ];
    }
}
