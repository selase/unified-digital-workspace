<?php

declare(strict_types=1);

namespace App\Modules\Forums\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Forums\Http\Requests\ForumPostStoreRequest;
use App\Modules\Forums\Http\Requests\ForumReactionStoreRequest;
use App\Modules\Forums\Http\Resources\ForumPostResource;
use App\Modules\Forums\Http\Resources\ForumReactionResource;
use App\Modules\Forums\Models\ForumPost;
use App\Modules\Forums\Models\ForumThread;
use App\Modules\Forums\Services\ForumMentionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ForumPostController extends Controller
{
    public function store(ForumPostStoreRequest $request, ForumThread $thread, ForumMentionService $mentionService): JsonResponse
    {
        if ($thread->locked_at) {
            return response()->json([
                'message' => 'Thread is locked.',
            ], 422);
        }

        $post = $thread->posts()->create([
            'user_id' => (string) $request->user()?->uuid,
            'body' => (string) $request->validated('body'),
        ]);

        $mentionService->notifyFromBody(
            body: (string) $request->validated('body'),
            thread: $thread,
            actorUuid: (string) $request->user()?->uuid,
            postId: $post->id,
        );

        return (new ForumPostResource($post))
            ->response()
            ->setStatusCode(201);
    }

    public function reply(ForumPostStoreRequest $request, ForumPost $post, ForumMentionService $mentionService): JsonResponse
    {
        $thread = $post->thread;

        if (! $thread || $thread->locked_at) {
            return response()->json([
                'message' => 'Thread is locked.',
            ], 422);
        }

        $reply = $thread->posts()->create([
            'user_id' => (string) $request->user()?->uuid,
            'parent_id' => $post->id,
            'body' => (string) $request->validated('body'),
        ]);

        $mentionService->notifyFromBody(
            body: (string) $request->validated('body'),
            thread: $thread,
            actorUuid: (string) $request->user()?->uuid,
            postId: $reply->id,
        );

        return (new ForumPostResource($reply))
            ->response()
            ->setStatusCode(201);
    }

    public function react(ForumReactionStoreRequest $request, ForumPost $post): JsonResponse
    {
        $reaction = $post->reactions()->firstOrCreate([
            'user_id' => (string) $request->user()?->uuid,
            'type' => (string) $request->validated('type'),
        ]);

        return (new ForumReactionResource($reaction))
            ->response()
            ->setStatusCode(201);
    }

    public function unreact(Request $request, ForumPost $post): JsonResponse
    {
        abort_if(! $request->user()?->can('forums.post'), 403);

        $type = (string) $request->query('type', 'like');

        $post->reactions()
            ->where('user_id', (string) $request->user()?->uuid)
            ->where('type', $type)
            ->delete();

        return response()->json([], 204);
    }

    public function markBest(Request $request, ForumPost $post): ForumPostResource
    {
        abort_if(! $request->user()?->can('forums.moderate'), 403);

        $thread = $post->thread;

        if ($thread) {
            $thread->posts()->update(['is_best_answer' => false]);
        }

        $post->is_best_answer = true;
        $post->save();

        return new ForumPostResource($post);
    }
}
