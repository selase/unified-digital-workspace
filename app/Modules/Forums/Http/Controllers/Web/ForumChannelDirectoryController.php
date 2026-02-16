<?php

declare(strict_types=1);

namespace App\Modules\Forums\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Forums\Models\ForumChannel;
use App\Modules\Forums\Models\ForumThread;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class ForumChannelDirectoryController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('forums.view'), 403);

        $channels = ForumChannel::query()
            ->withCount('threads')
            ->withCount([
                'threads as flagged_threads_count' => fn ($query) => $query->where('status', ForumThread::STATUS_FLAGGED),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20);

        return view('forums::channels', [
            'channels' => $channels,
        ]);
    }
}
