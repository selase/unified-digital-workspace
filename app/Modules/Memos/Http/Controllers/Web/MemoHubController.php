<?php

declare(strict_types=1);

namespace App\Modules\Memos\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Memos\Models\Memo;
use App\Modules\Memos\Models\MemoAction;
use App\Modules\Memos\Models\MemoRecipient;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class MemoHubController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('memos.view'), 403);

        $recentMemos = Memo::query()
            ->with(['sender:id,first_name,last_name,email', 'recipients', 'actions'])
            ->latest('updated_at')
            ->limit(10)
            ->get();

        $statusCounts = Memo::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $acknowledgementRequiredCount = MemoRecipient::query()
            ->where('requires_ack', true)
            ->count();

        $pendingAcknowledgementCount = MemoRecipient::query()
            ->where('requires_ack', true)
            ->whereNull('acknowledged_at')
            ->count();

        $openActionCount = MemoAction::query()
            ->where('status', 'open')
            ->count();

        return view('memos::index', [
            'recentMemos' => $recentMemos,
            'statusCounts' => $statusCounts,
            'acknowledgementRequiredCount' => $acknowledgementRequiredCount,
            'pendingAcknowledgementCount' => $pendingAcknowledgementCount,
            'openActionCount' => $openActionCount,
        ]);
    }
}
