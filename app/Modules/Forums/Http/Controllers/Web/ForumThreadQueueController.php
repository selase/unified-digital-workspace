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

        $search = mb_trim((string) $request->string('search'));
        $status = (string) $request->string('status');

        $threads = ForumThread::query()
            ->with('channel:id,name,slug')
            ->withCount('posts')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('title', 'like', "%{$search}%");
            })
            ->when(in_array($status, ['open', 'closed', 'flagged'], true), function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('forums::threads', [
            'threads' => $threads,
            'search' => $search,
            'status' => $status,
        ]);
    }
}
