<?php

declare(strict_types=1);

namespace App\Modules\Forums\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Forums\Http\Requests\ForumModerateThreadRequest;
use App\Modules\Forums\Http\Requests\ForumThreadStoreRequest;
use App\Modules\Forums\Http\Resources\ForumModerationLogResource;
use App\Modules\Forums\Http\Resources\ForumThreadResource;
use App\Modules\Forums\Models\ForumChannel;
use App\Modules\Forums\Models\ForumModerationLog;
use App\Modules\Forums\Models\ForumThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ForumThreadController extends Controller
{
    public function store(ForumThreadStoreRequest $request, ForumChannel $channel): JsonResponse
    {
        $payload = $request->validated();
        $slug = Str::lower((string) ($payload['slug'] ?? Str::slug((string) $payload['title'])));

        $slugExists = ForumThread::query()
            ->where('channel_id', $channel->id)
            ->where('slug', $slug)
            ->exists();

        if ($slugExists) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'slug' => ['The slug has already been taken in this channel.'],
                ],
            ], 422);
        }

        $threadData = collect($payload)->except('body')->all();

        $thread = ForumThread::create([
            ...$threadData,
            'channel_id' => $channel->id,
            'user_id' => (string) $request->user()?->uuid,
            'slug' => $slug,
        ]);

        $thread->posts()->create([
            'user_id' => (string) $request->user()?->uuid,
            'body' => (string) $payload['body'],
        ]);

        return (new ForumThreadResource($thread->load('posts.reactions')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, ForumThread $thread): ForumThreadResource
    {
        abort_if(! $request->user()?->can('forums.view'), 403);

        $thread->load([
            'posts' => fn ($query) => $query->whereNull('parent_id')->latest(),
            'posts.reactions',
            'posts.replies.reactions',
        ]);

        return new ForumThreadResource($thread);
    }

    public function moderate(ForumModerateThreadRequest $request, ForumThread $thread): JsonResponse
    {
        $action = $request->validated('action');
        $reason = $request->validated('reason');

        if ($action === 'pin') {
            $thread->pinned_at = now();
        }

        if ($action === 'unpin') {
            $thread->pinned_at = null;
        }

        if ($action === 'lock') {
            $thread->locked_at = now();
            $thread->status = ForumThread::STATUS_LOCKED;
        }

        if ($action === 'unlock') {
            $thread->locked_at = null;
            $thread->status = ForumThread::STATUS_OPEN;
        }

        if ($action === 'flag') {
            $thread->status = ForumThread::STATUS_FLAGGED;
        }

        if ($action === 'delete') {
            $thread->status = ForumThread::STATUS_DELETED;
        }

        $thread->save();

        $log = ForumModerationLog::create([
            'thread_id' => $thread->id,
            'moderator_id' => (string) $request->user()?->uuid,
            'action' => $action,
            'reason' => $reason,
            'metadata' => [
                'thread_uuid' => $thread->uuid,
            ],
        ]);

        return response()->json([
            'thread' => new ForumThreadResource($thread),
            'moderation_log' => new ForumModerationLogResource($log),
        ]);
    }
}
