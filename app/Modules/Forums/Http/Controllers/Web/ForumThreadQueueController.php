<?php

declare(strict_types=1);

namespace App\Modules\Forums\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Forums\Models\ForumThread;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class ForumThreadQueueController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('forums.view'), 403);

        $threads = ForumThread::query()
            ->with('channel:id,name,slug')
            ->withCount('posts')
            ->latest('updated_at')
            ->paginate(20);

        return view('forums::threads', [
            'threads' => $threads,
        ]);
    }
}
