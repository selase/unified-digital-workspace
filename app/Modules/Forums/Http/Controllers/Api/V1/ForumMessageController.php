<?php

declare(strict_types=1);

namespace App\Modules\Forums\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Forums\Http\Requests\ForumMessageStoreRequest;
use App\Modules\Forums\Http\Resources\ForumMessageResource;
use App\Modules\Forums\Models\ForumMessage;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class ForumMessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_if(! $request->user()?->can('forums.view'), 403);

        $validated = Validator::make($request->query(), [
            'box' => ['nullable', 'string', 'in:all,inbox,sent'],
            'scope' => ['nullable', 'string', 'in:direct,unit,department,directorate,organization'],
            'q' => ['nullable', 'string', 'max:255'],
            'unread' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ])->validate();

        $userId = (string) $request->user()?->uuid;
        $box = (string) ($validated['box'] ?? 'all');
        $searchQuery = (string) ($validated['q'] ?? '');
        $scope = $validated['scope'] ?? null;
        $unreadOnly = filter_var($validated['unread'] ?? false, FILTER_VALIDATE_BOOL);

        $messages = ForumMessage::query()
            ->with('recipients')
            ->where(function ($query) use ($box, $unreadOnly, $userId): void {
                if ($box === 'sent') {
                    $query->where('sender_id', $userId);

                    return;
                }

                if ($box === 'inbox' || $unreadOnly) {
                    $query->whereHas('recipients', function ($recipientQuery) use ($unreadOnly, $userId): void {
                        $recipientQuery
                            ->where('user_id', $userId)
                            ->whereNull('deleted_at');

                        if ($unreadOnly) {
                            $recipientQuery->whereNull('read_at');
                        }
                    });

                    return;
                }

                $query
                    ->where('sender_id', $userId)
                    ->orWhereHas('recipients', fn ($recipientQuery) => $recipientQuery
                        ->where('user_id', $userId)
                        ->whereNull('deleted_at'));
            });

        if ($scope !== null) {
            $messages->where('visibility->scope', $scope);
        }

        if ($searchQuery !== '') {
            $messages->where(function ($query) use ($searchQuery): void {
                $query->where('subject', 'like', "%{$searchQuery}%")
                    ->orWhere('body', 'like', "%{$searchQuery}%");
            });
        }

        $messages = $messages
            ->latest()
            ->paginate((int) ($validated['per_page'] ?? 15));

        return ForumMessageResource::collection($messages)->response();
    }

    public function store(ForumMessageStoreRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $tenantId = app(TenantContext::class)->activeTenantId();

        $requestedRecipientIds = collect($payload['recipient_user_ids'])
            ->map(fn ($id): string => (string) $id)
            ->filter()
            ->unique()
            ->values();

        $recipientIds = User::query()
            ->whereIn('uuid', $requestedRecipientIds)
            ->when($tenantId !== null, fn ($query) => $query->whereHas('tenants', fn ($tenantQuery) => $tenantQuery->where('tenants.id', $tenantId)))
            ->pluck('uuid');

        if ($recipientIds->count() !== $requestedRecipientIds->count()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'recipient_user_ids' => ['One or more recipients are not members of the current tenant.'],
                ],
            ], 422);
        }

        if ($recipientIds->isEmpty()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'recipient_user_ids' => ['No valid recipients found in the current tenant.'],
                ],
            ], 422);
        }

        $message = ForumMessage::create([
            'sender_id' => (string) $request->user()?->uuid,
            'subject' => (string) $payload['subject'],
            'body' => (string) $payload['body'],
            'visibility' => [
                ...($payload['visibility'] ?? []),
                'scope' => $payload['visibility']['scope'] ?? 'direct',
            ],
            'metadata' => $payload['metadata'] ?? null,
        ]);

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
