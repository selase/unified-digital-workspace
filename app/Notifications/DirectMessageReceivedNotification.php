<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Modules\Forums\Models\ForumMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class DirectMessageReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $messageUuid,
        public readonly string $subject,
        public readonly string $senderName,
        public readonly string $recipientRole = 'to',
    ) {}

    public static function fromMessage(ForumMessage $message, string $senderName, string $recipientRole = 'to'): self
    {
        return new self(
            messageUuid: (string) $message->uuid,
            subject: (string) $message->subject,
            senderName: $senderName,
            recipientRole: $recipientRole,
        );
    }

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
        $roleBadge = mb_strtoupper($this->recipientRole);

        return (new MailMessage)
            ->subject("[Direct Message - {$roleBadge}] {$this->subject}")
            ->greeting('New Direct Message')
            ->line("{$this->senderName} sent you a direct message ({$roleBadge}).")
            ->line("Subject: {$this->subject}")
            ->action('Read Message', url("/forums/messages/{$this->messageUuid}"));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'direct_message_received',
            'module' => 'forums',
            'title' => "Message from {$this->senderName}: {$this->subject}",
            'subject' => $this->subject,
            'sender_name' => $this->senderName,
            'recipient_role' => $this->recipientRole,
            'message_uuid' => $this->messageUuid,
            'action_url' => "/forums/messages/{$this->messageUuid}",
            'dispatched_at' => now()->toInstitutionalDateTime(),
        ];
    }
}
