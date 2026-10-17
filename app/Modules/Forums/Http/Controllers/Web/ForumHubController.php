<?php

declare(strict_types=1);

namespace App\Modules\Forums\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Forums\Models\ForumChannel;
use App\Modules\Forums\Models\ForumMessageRecipient;
use App\Modules\Forums\Models\ForumModerationLog;
use App\Modules\Forums\Models\ForumPost;
use App\Modules\Forums\Models\ForumThread;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class ForumHubController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('forums.view'), 403);

        $userUuid = (string) $request->user()->uuid;

        $metrics = [
            'channels' => ForumChannel::query()->count(),
            'threads' => ForumThread::query()->count(),
            'posts' => ForumPost::query()->count(),
            'flagged' => ForumThread::query()->where('status', ForumThread::STATUS_FLAGGED)->count(),
            'unread_messages' => ForumMessageRecipient::query()
                ->where('user_id', $userUuid)
                ->whereNull('read_at')
                ->whereNull('deleted_at')
                ->count(),
        ];

        $channels = ForumChannel::query()
            ->withCount('threads')
            ->orderBy('sort_order')
            ->limit(6)
            ->get();

        $latestThreads = ForumThread::query()
            ->with(['channel:id,name,slug,uuid', 'author:uuid,first_name,last_name'])
            ->withCount('posts')
            ->latest('updated_at')
            ->limit(8)
            ->get();

        $pinnedThreads = ForumThread::query()
            ->with(['channel:id,name,slug,uuid', 'author:uuid,first_name,last_name'])
            ->withCount('posts')
            ->whereNotNull('pinned_at')
            ->latest('pinned_at')
            ->limit(4)
            ->get();

        $flaggedThreads = ForumThread::query()
            ->with(['channel:id,name,slug,uuid', 'author:uuid,first_name,last_name'])
            ->where('status', ForumThread::STATUS_FLAGGED)
            ->latest('updated_at')
            ->limit(5)
            ->get();

        $latestModerationLogs = ForumModerationLog::query()
            ->with(['thread:id,title,uuid', 'moderator:uuid,first_name,last_name'])
            ->latest('created_at')
            ->limit(5)
            ->get();

        return view('forums::hub', [
            'metrics' => $metrics,
            'channels' => $channels,
            'latestThreads' => $latestThreads,
            'pinnedThreads' => $pinnedThreads,
            'flaggedThreads' => $flaggedThreads,
            'latestModerationLogs' => $latestModerationLogs,
        ]);
    }
}
