<?php

declare(strict_types=1);

namespace App\Modules\Forums\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Forums\Models\ForumChannel;
use App\Modules\Forums\Models\ForumModerationLog;
use App\Modules\Forums\Models\ForumPost;
use App\Modules\Forums\Models\ForumThread;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ForumThreadWebController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('forums.view'), 403);

        $search = mb_trim((string) $request->string('search'));
        $status = (string) $request->string('status');
        $channelSlug = (string) $request->string('channel');

        $threads = ForumThread::query()
            ->with(['channel:id,name,slug,uuid', 'author:uuid,first_name,last_name'])
            ->withCount('posts')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('title', 'like', "%{$search}%");
            })
            ->when(in_array($status, ['open', 'locked', 'flagged'], true), function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->when($channelSlug !== '', function ($query) use ($channelSlug): void {
                $query->whereHas('channel', fn ($q) => $q->where('slug', $channelSlug));
            })
            ->orderByRaw('pinned_at IS NULL, pinned_at DESC')
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        $channels = ForumChannel::query()->orderBy('name')->get(['id', 'name', 'slug', 'uuid']);

        return view('forums::threads', [
            'threads' => $threads,
            'channels' => $channels,
            'search' => $search,
            'status' => $status,
            'channelSlug' => $channelSlug,
        ]);
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()?->can('forums.post'), 403);

        $channelId = $request->input('channel_id');
        $channels = ForumChannel::query()->where('is_locked', false)->orderBy('sort_order')->orderBy('name')->get();

        return view('forums::threads-create', [
            'channels' => $channels,
            'selectedChannelId' => $channelId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if(! $request->user()?->can('forums.post'), 403);

        $validated = $request->validate([
            'channel_id' => ['required', 'integer', 'exists:forum_channels,id'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'min:5'],
            'tags' => ['nullable', 'string'],
        ]);

        $tagsArray = null;
        if (! empty($validated['tags'])) {
            $tagsArray = array_values(array_filter(array_map('trim', explode(',', $validated['tags']))));
        }

        $thread = ForumThread::create([
            'channel_id' => $validated['channel_id'],
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title']),
            'user_id' => (string) $request->user()->uuid,
            'status' => ForumThread::STATUS_OPEN,
            'tags' => $tagsArray,
        ]);

        ForumPost::create([
            'thread_id' => $thread->id,
            'user_id' => (string) $request->user()->uuid,
            'body' => $validated['body'],
        ]);

        return redirect()
            ->route('forums.threads.show', $thread)
            ->with('status', 'success')
            ->with('message', 'Discussion thread published successfully.');
    }

    public function show(Request $request, ForumThread $thread): View
    {
        abort_if(! $request->user()?->can('forums.view'), 403);

        $thread->load([
            'channel:id,name,slug,uuid,is_locked',
            'author:uuid,first_name,last_name,email',
        ]);

        $posts = $thread->posts()
            ->with(['author:uuid,first_name,last_name,email'])
            ->oldest('created_at')
            ->paginate(25);

        return view('forums::threads-show', [
            'thread' => $thread,
            'posts' => $posts,
        ]);
    }

    public function reply(Request $request, ForumThread $thread): RedirectResponse
    {
        abort_if(! $request->user()?->can('forums.post'), 403);

        if ($thread->status === ForumThread::STATUS_LOCKED || $thread->channel?->is_locked) {
            return redirect()
                ->route('forums.threads.show', $thread)
                ->with('status', 'danger')
                ->with('message', 'This thread is locked for replies.');
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2'],
        ]);

        ForumPost::create([
            'thread_id' => $thread->id,
            'user_id' => (string) $request->user()->uuid,
            'body' => $validated['body'],
        ]);

        $thread->touch();

        return redirect()
            ->route('forums.threads.show', $thread)
            ->with('status', 'success')
            ->with('message', 'Reply posted successfully.');
    }

    public function lock(Request $request, ForumThread $thread): RedirectResponse
    {
        abort_if(! $request->user()?->can('forums.moderate'), 403);

        $isCurrentlyLocked = $thread->status === ForumThread::STATUS_LOCKED;
        $newStatus = $isCurrentlyLocked ? ForumThread::STATUS_OPEN : ForumThread::STATUS_LOCKED;
        $lockedAt = $isCurrentlyLocked ? null : now();

        $thread->update([
            'status' => $newStatus,
            'locked_at' => $lockedAt,
        ]);

        ForumModerationLog::create([
            'thread_id' => $thread->id,
            'moderator_id' => (string) $request->user()->uuid,
            'action' => $isCurrentlyLocked ? 'unlock_thread' : 'lock_thread',
            'reason' => $request->string('reason', 'Administrative decision')->toString(),
            'created_at' => now(),
        ]);

        $statusMessage = $isCurrentlyLocked ? 'Thread unlocked for contributions.' : 'Thread locked successfully.';

        return redirect()
            ->route('forums.threads.show', $thread)
            ->with('status', 'success')
            ->with('message', $statusMessage);
    }

    public function pin(Request $request, ForumThread $thread): RedirectResponse
    {
        abort_if(! $request->user()?->can('forums.moderate'), 403);

        $isCurrentlyPinned = $thread->pinned_at !== null;
        $pinnedAt = $isCurrentlyPinned ? null : now();

        $thread->update([
            'pinned_at' => $pinnedAt,
        ]);

        ForumModerationLog::create([
            'thread_id' => $thread->id,
            'moderator_id' => (string) $request->user()->uuid,
            'action' => $isCurrentlyPinned ? 'unpin_thread' : 'pin_thread',
            'reason' => $request->string('reason', 'Important notice visibility')->toString(),
            'created_at' => now(),
        ]);

        $statusMessage = $isCurrentlyPinned ? 'Thread unpinned from top of channel.' : 'Thread pinned to top of channel.';

        return redirect()
            ->route('forums.threads.show', $thread)
            ->with('status', 'success')
            ->with('message', $statusMessage);
    }

    public function flag(Request $request, ForumThread $thread): RedirectResponse
    {
        abort_if(! $request->user()?->can('forums.moderate'), 403);

        $isFlagged = $thread->status === ForumThread::STATUS_FLAGGED;
        $newStatus = $isFlagged ? ForumThread::STATUS_OPEN : ForumThread::STATUS_FLAGGED;

        $thread->update([
            'status' => $newStatus,
        ]);

        ForumModerationLog::create([
            'thread_id' => $thread->id,
            'moderator_id' => (string) $request->user()->uuid,
            'action' => $isFlagged ? 'resolve_flag' : 'flag_thread',
            'reason' => $request->string('reason', 'Policy review compliance')->toString(),
            'created_at' => now(),
        ]);

        $statusMessage = $isFlagged ? 'Flag cleared and thread restored to active status.' : 'Thread flagged for content moderation.';

        return redirect()
            ->route('forums.threads.show', $thread)
            ->with('status', 'success')
            ->with('message', $statusMessage);
    }
}
