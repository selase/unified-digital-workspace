<?php

declare(strict_types=1);

namespace App\Modules\Forums\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Forums\Models\ForumMessage;
use App\Modules\Forums\Models\ForumMessageRecipient;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class ForumMessageCenterController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('forums.view'), 403);

        $userUuid = (string) $request->user()?->uuid;

        $messages = ForumMessage::query()
            ->with(['sender:id,first_name,last_name'])
            ->withCount('recipients')
            ->where(function ($query) use ($userUuid): void {
                $query
                    ->where('sender_id', $userUuid)
                    ->orWhereHas('recipients', fn ($recipientQuery) => $recipientQuery
                        ->where('user_id', $userUuid)
                        ->whereNull('deleted_at'));
            })
            ->latest('updated_at')
            ->paginate(20);

        $unreadCount = ForumMessageRecipient::query()
            ->where('user_id', $userUuid)
            ->whereNull('read_at')
            ->whereNull('deleted_at')
            ->count();

        return view('forums::messages', [
            'messages' => $messages,
            'unreadCount' => $unreadCount,
        ]);
    }
}
