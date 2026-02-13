<?php

declare(strict_types=1);

namespace App\Notifications\Forums;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class ForumMentionedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $threadUuid,
        private readonly string $actorUuid,
        private readonly string $tenantId,
        private readonly string $excerpt,
        private readonly ?int $postId = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'module' => 'forums',
            'type' => 'forum_mention',
            'thread_uuid' => $this->threadUuid,
            'actor_uuid' => $this->actorUuid,
            'tenant_id' => $this->tenantId,
            'post_id' => $this->postId,
            'excerpt' => $this->excerpt,
        ];
    }
}
