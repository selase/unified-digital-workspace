<?php

declare(strict_types=1);

namespace App\Modules\Forums\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Forums\Http\Requests\ForumChannelStoreRequest;
use App\Modules\Forums\Http\Requests\ForumChannelUpdateRequest;
use App\Modules\Forums\Http\Resources\ForumChannelResource;
use App\Modules\Forums\Models\ForumChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ForumChannelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_if(! $request->user()?->can('forums.view'), 403);

        $channels = ForumChannel::query()
            ->withCount('threads')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return ForumChannelResource::collection($channels)->response();
    }

    public function store(ForumChannelStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $slug = Str::lower((string) ($data['slug'] ?? Str::slug((string) $data['name'])));

        $slugExists = ForumChannel::query()
            ->where('slug', $slug)
            ->exists();

        if ($slugExists) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'slug' => ['The slug has already been taken.'],
                ],
            ], 422);
        }

        $channel = ForumChannel::create([
            ...$data,
            'slug' => $slug,
        ]);

        return (new ForumChannelResource($channel))
            ->response()
            ->setStatusCode(201);
    }

    public function update(ForumChannelUpdateRequest $request, ForumChannel $channel): ForumChannelResource|JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('slug', $data) && $data['slug'] !== null) {
            $slug = Str::lower((string) $data['slug']);

            $slugExists = ForumChannel::query()
                ->where('slug', $slug)
                ->where('id', '!=', $channel->id)
                ->exists();

            if ($slugExists) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => [
                        'slug' => ['The slug has already been taken.'],
                    ],
                ], 422);
            }

            $data['slug'] = $slug;
        }

        $channel->fill($data);
        $channel->save();

        return new ForumChannelResource($channel);
    }

    public function destroy(Request $request, ForumChannel $channel): JsonResponse
    {
        abort_if(! $request->user()?->can('forums.moderate'), 403);

        $channel->delete();

        return response()->json([], 204);
    }
}
