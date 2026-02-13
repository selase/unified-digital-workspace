<?php

declare(strict_types=1);

namespace App\Modules\Forums\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Forums\Http\Requests\ForumMessageStoreRequest;
use App\Modules\Forums\Http\Resources\ForumMessageResource;
use App\Modules\Forums\Models\ForumMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ForumMessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_if(! $request->user()?->can('forums.view'), 403);

        $userId = (string) $request->user()?->uuid;

        $messages = ForumMessage::query()
            ->with('recipients')
            ->where('sender_id', $userId)
            ->orWhereHas('recipients', fn ($query) => $query->where('user_id', $userId)->whereNull('deleted_at'))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return ForumMessageResource::collection($messages)->response();
    }

    public function store(ForumMessageStoreRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $message = ForumMessage::create([
            'sender_id' => (string) $request->user()?->uuid,
            'subject' => (string) $payload['subject'],
            'body' => (string) $payload['body'],
            'visibility' => $payload['visibility'] ?? null,
            'metadata' => $payload['metadata'] ?? null,
        ]);

        $recipientIds = collect($payload['recipient_user_ids'])
            ->map(fn ($id): string => (string) $id)
            ->filter()
            ->unique()
            ->values();

        foreach ($recipientIds as $recipientId) {
            $message->recipients()->create([
                'user_id' => $recipientId,
            ]);
        }

        return (new ForumMessageResource($message->load('recipients')))
            ->response()
            ->setStatusCode(201);
    }

    public function read(Request $request, ForumMessage $message): ForumMessageResource
    {
        abort_if(! $request->user()?->can('forums.view'), 403);

        $recipient = $message->recipients()
            ->where('user_id', (string) $request->user()?->uuid)
            ->firstOrFail();

        if (! $recipient->read_at) {
            $recipient->read_at = now();
            $recipient->save();
        }

        return new ForumMessageResource($message->load('recipients'));
    }
}
