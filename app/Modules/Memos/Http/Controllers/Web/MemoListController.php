<?php

declare(strict_types=1);

namespace App\Modules\Memos\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Memos\Models\Memo;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class MemoListController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('memos.view'), 403);

        $search = mb_trim((string) $request->string('search'));
        $status = (string) $request->string('status');

        $memos = Memo::query()
            ->with('sender:uuid,first_name,last_name,email')
            ->withCount('recipients')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('subject', 'like', "%{$search}%");
            })
            ->when(in_array($status, [Memo::STATUS_DRAFT, Memo::STATUS_PENDING, Memo::STATUS_SENT, Memo::STATUS_ACKNOWLEDGED, Memo::STATUS_CLOSED], true), function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('memos::memos', [
            'memos' => $memos,
            'search' => $search,
            'status' => $status,
        ]);
    }
}
