<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Modules\Memos\Models\Memo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class MemoDispatchedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $memoUuid,
        public readonly string $subject,
        public readonly string $senderName,
        public readonly string $recipientRole = 'to',
    ) {}

    public static function fromMemo(Memo $memo, string $senderName, string $recipientRole = 'to'): self
    {
        return new self(
            memoUuid: (string) $memo->uuid,
            subject: (string) $memo->subject,
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
            ->subject("[Official Memo - {$roleBadge}] {$this->subject}")
            ->greeting('Official Institutional Correspondence')
            ->line("You have received an official memorandum from {$this->senderName} (Addressed to you as: {$roleBadge}).")
            ->line("Subject: {$this->subject}")
            ->action('Open Memorandum', url("/memos/{$this->memoUuid}"))
            ->line('Please review and acknowledge or minute as appropriate.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'memo_dispatched',
            'module' => 'memos',
            'title' => "Official Memo ({$this->recipientRole}): {$this->subject}",
            'subject' => $this->subject,
            'sender_name' => $this->senderName,
            'recipient_role' => $this->recipientRole,
            'memo_uuid' => $this->memoUuid,
            'action_url' => "/memos/{$this->memoUuid}",
            'dispatched_at' => now()->toInstitutionalDateTime(),
        ];
    }
}
