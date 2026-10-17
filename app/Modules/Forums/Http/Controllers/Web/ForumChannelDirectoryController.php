<?php

declare(strict_types=1);

namespace App\Modules\Forums\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Forums\Models\ForumChannel;
use App\Modules\Forums\Models\ForumThread;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ForumChannelDirectoryController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('forums.view'), 403);

        $search = mb_trim((string) $request->string('search'));

        $channels = ForumChannel::query()
            ->withCount('threads')
            ->withCount([
                'threads as flagged_threads_count' => fn ($query) => $query->where('status', ForumThread::STATUS_FLAGGED),
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('forums::channels', [
            'channels' => $channels,
            'search' => $search,
        ]);
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()?->can('forums.moderate'), 403);

        return view('forums::channels-create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if(! $request->user()?->can('forums.moderate'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_locked' => ['nullable', 'boolean'],
        ]);

        $channel = ForumChannel::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_locked' => $request->boolean('is_locked'),
        ]);

        return redirect()
            ->route('forums.channels.show', $channel)
            ->with('status', 'success')
            ->with('message', "Discussion channel '{$channel->name}' established successfully.");
    }

    public function show(Request $request, ForumChannel $channel): View
    {
        abort_if(! $request->user()?->can('forums.view'), 403);

        $search = mb_trim((string) $request->string('search'));
        $status = (string) $request->string('status');

        $threads = $channel->threads()
            ->with(['author:uuid,first_name,last_name'])
            ->withCount('posts')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('title', 'like', "%{$search}%");
            })
            ->when(in_array($status, ['open', 'locked', 'flagged'], true), function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->orderByRaw('pinned_at IS NULL, pinned_at DESC')
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('forums::channels-show', [
            'channel' => $channel,
            'threads' => $threads,
            'search' => $search,
            'status' => $status,
        ]);
    }
}
