<?php

declare(strict_types=1);

namespace App\Modules\Forums\Services;

use App\Models\User;
use App\Modules\Forums\Models\ForumThread;
use App\Notifications\Forums\ForumMentionedNotification;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class ForumMentionService
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function notifyFromBody(string $body, ForumThread $thread, string $actorUuid, ?int $postId = null): int
    {
        $tenantId = $this->tenantContext->activeTenantId();

        if (! $tenantId) {
            return 0;
        }

        $mentionedUuids = $this->extractMentionedUuids($body)
            ->reject(fn (string $uuid): bool => $uuid === $actorUuid)
            ->values();

        if ($mentionedUuids->isEmpty()) {
            return 0;
        }

        $users = User::query()
            ->whereIn('uuid', $mentionedUuids->all())
            ->whereHas('tenants', fn ($query) => $query->where('tenants.id', $tenantId))
            ->get();

        foreach ($users as $user) {
            $user->notify(new ForumMentionedNotification(
                threadUuid: (string) $thread->uuid,
                actorUuid: $actorUuid,
                tenantId: $tenantId,
                excerpt: Str::limit(mb_trim(strip_tags($body)), 180),
                postId: $postId,
            ));
        }

        return $users->count();
    }

    /**
     * @return Collection<int, string>
     */
    private function extractMentionedUuids(string $body): Collection
    {
        preg_match_all('/@([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})/', $body, $matches);

        return collect($matches[1] ?? [])
            ->map(fn ($uuid): string => Str::lower((string) $uuid))
            ->unique()
            ->values();
    }
}
