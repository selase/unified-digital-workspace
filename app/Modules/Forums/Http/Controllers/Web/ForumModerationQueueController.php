<?php

declare(strict_types=1);

namespace App\Modules\Forums\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Forums\Models\ForumModerationLog;
use App\Modules\Forums\Models\ForumThread;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class ForumModerationQueueController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('forums.moderate'), 403);

        $flaggedThreads = ForumThread::query()
            ->where('status', ForumThread::STATUS_FLAGGED)
            ->with('channel:id,name')
            ->withCount('posts')
            ->latest('updated_at')
            ->paginate(20);

        $latestLogs = ForumModerationLog::query()
            ->with(['thread:id,title', 'moderator:id,first_name,last_name'])
            ->latest('created_at')
            ->limit(10)
            ->get();

        $overview = [
            'flagged_threads' => ForumThread::query()->where('status', ForumThread::STATUS_FLAGGED)->count(),
            'locked_threads' => ForumThread::query()->whereNotNull('locked_at')->count(),
            'pinned_threads' => ForumThread::query()->whereNotNull('pinned_at')->count(),
            'actions_last_24h' => ForumModerationLog::query()
                ->where('created_at', '>=', now()->subDay())
                ->count(),
        ];

        return view('forums::moderation', [
            'flaggedThreads' => $flaggedThreads,
            'latestLogs' => $latestLogs,
            'overview' => $overview,
        ]);
    }
}
