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
use App\Modules\Forums\Services\ForumMentionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ForumThreadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_if(! $request->user()?->can('forums.view'), 403);

        $threads = ForumThread::query()
            ->with('channel')
            ->withCount('posts');

        if ($request->filled('channel')) {
            $channel = (string) $request->query('channel');

            $threads->whereHas('channel', function ($query) use ($channel): void {
                $query->where('uuid', $channel)
                    ->orWhere('slug', $channel);
            });
        }

        if ($request->filled('tag')) {
            $threads->whereJsonContains('tags', (string) $request->query('tag'));
        }

        if ($request->filled('user')) {
            $threads->where('user_id', (string) $request->query('user'));
        }

        if ($request->filled('status')) {
            $threads->where('status', (string) $request->query('status'));
        }

        $result = $threads
            ->latest('updated_at')
            ->paginate($request->integer('per_page', 15));

        return ForumThreadResource::collection($result)->response();
    }

    public function store(ForumThreadStoreRequest $request, ForumChannel $channel, ForumMentionService $mentionService): JsonResponse
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

        $openingPost = $thread->posts()->create([
            'user_id' => (string) $request->user()?->uuid,
            'body' => (string) $payload['body'],
        ]);

        $mentionService->notifyFromBody(
            body: (string) $payload['body'],
            thread: $thread,
            actorUuid: (string) $request->user()?->uuid,
            postId: $openingPost->id,
        );

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

    public function flaggedQueue(Request $request): JsonResponse
    {
        abort_if(! $request->user()?->can('forums.moderate'), 403);

        $threads = ForumThread::query()
            ->where('status', ForumThread::STATUS_FLAGGED)
            ->with('channel')
            ->withCount('posts')
            ->latest('updated_at')
            ->paginate($request->integer('per_page', 15));

        return ForumThreadResource::collection($threads)->response();
    }

    public function moderationLogs(Request $request): JsonResponse
    {
        abort_if(! $request->user()?->can('forums.moderate'), 403);

        $query = ForumModerationLog::query()->latest('created_at');

        if ($request->filled('action')) {
            $query->where('action', (string) $request->query('action'));
        }

        $logs = $query->paginate($request->integer('per_page', 20));

        return ForumModerationLogResource::collection($logs)->response();
    }
}
