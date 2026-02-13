<?php

declare(strict_types=1);

namespace App\Modules\Forums\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Forums\Models\ForumChannel;
use App\Modules\Forums\Models\ForumModerationLog;
use App\Modules\Forums\Models\ForumThread;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class ForumHubController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('forums.view'), 403);

        $channels = ForumChannel::query()
            ->withCount('threads')
            ->orderBy('sort_order')
            ->limit(6)
            ->get();

        $latestThreads = ForumThread::query()
            ->with('channel')
            ->latest('updated_at')
            ->limit(8)
            ->get();

        $flaggedThreads = ForumThread::query()
            ->where('status', ForumThread::STATUS_FLAGGED)
            ->latest('updated_at')
            ->limit(5)
            ->get();

        $latestModerationLogs = ForumModerationLog::query()
            ->latest('created_at')
            ->limit(5)
            ->get();

        return view('forums::hub', [
            'channels' => $channels,
            'latestThreads' => $latestThreads,
            'flaggedThreads' => $flaggedThreads,
            'latestModerationLogs' => $latestModerationLogs,
        ]);
    }
}
