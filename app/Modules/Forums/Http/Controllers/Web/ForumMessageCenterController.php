<?php

declare(strict_types=1);

namespace App\Modules\Forums\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Forums\Models\ForumMessage;
use App\Modules\Forums\Models\ForumMessageRecipient;
use App\Notifications\DirectMessageReceivedNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ForumMessageCenterController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('forums.view'), 403);

        $search = mb_trim((string) $request->string('search'));
        $userUuid = (string) $request->user()?->uuid;

        $messages = ForumMessage::query()
            ->with(['sender:uuid,first_name,last_name,email'])
            ->withCount('recipients')
            ->where(function ($query) use ($userUuid): void {
                $query
                    ->where('sender_id', $userUuid)
                    ->orWhereHas('recipients', fn ($recipientQuery) => $recipientQuery
                        ->where('user_id', $userUuid)
                        ->whereNull('deleted_at'));
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('subject', 'like', "%{$search}%");
            })
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        $unreadCount = ForumMessageRecipient::query()
            ->where('user_id', $userUuid)
            ->whereNull('read_at')
            ->whereNull('deleted_at')
            ->count();

        return view('forums::messages', [
            'messages' => $messages,
            'unreadCount' => $unreadCount,
            'search' => $search,
        ]);
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()?->can('forums.messages.send'), 403);

        $userUuid = (string) $request->user()?->uuid;
        $users = User::query()
            ->where('uuid', '!=', $userUuid)
            ->orderBy('first_name')
            ->get(['id', 'uuid', 'first_name', 'last_name', 'email']);

        return view('forums::messages-create', [
            'users' => $users,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $sender = $request->user();
        abort_if(! $sender?->can('forums.messages.send'), 403);

        $validated = $request->validate([
            'recipient_id' => ['nullable', 'string', 'exists:users,uuid'],
            'to_user_ids' => ['nullable', 'array'],
            'to_user_ids.*' => ['string', 'exists:users,uuid'],
            'cc_user_ids' => ['nullable', 'array'],
            'cc_user_ids.*' => ['string', 'exists:users,uuid'],
            'bcc_user_ids' => ['nullable', 'array'],
            'bcc_user_ids.*' => ['string', 'exists:users,uuid'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'min:3'],
        ]);

        /** @var array<string, string> $recipientRoleMap */
        $recipientRoleMap = [];

        foreach ($validated['bcc_user_ids'] ?? [] as $uuid) {
            $recipientRoleMap[(string) $uuid] = ForumMessageRecipient::ROLE_BCC;
        }
        foreach ($validated['cc_user_ids'] ?? [] as $uuid) {
            $recipientRoleMap[(string) $uuid] = ForumMessageRecipient::ROLE_CC;
        }
        foreach ($validated['to_user_ids'] ?? [] as $uuid) {
            $recipientRoleMap[(string) $uuid] = ForumMessageRecipient::ROLE_TO;
        }
        if (! empty($validated['recipient_id'])) {
            $recipientRoleMap[(string) $validated['recipient_id']] = ForumMessageRecipient::ROLE_TO;
        }

        if ($recipientRoleMap === []) {
            return back()->withErrors(['recipient_id' => 'Please select at least one recipient (To, CC, or BCC).'])->withInput();
        }

        $message = ForumMessage::create([
            'sender_id' => (string) $sender->uuid,
            'subject' => $validated['subject'],
            'body' => $validated['body'],
        ]);

        $senderName = $sender->displayName() ?: (string) $sender->email;
        $targetUsers = User::query()
            ->whereIn('uuid', array_keys($recipientRoleMap))
            ->get()
            ->keyBy('uuid');

        foreach ($recipientRoleMap as $recipientUuid => $role) {
            ForumMessageRecipient::create([
                'message_id' => $message->id,
                'user_id' => $recipientUuid,
                'role' => $role,
            ]);

            $targetUser = $targetUsers->get($recipientUuid);
            if ($targetUser instanceof User && (string) $targetUser->uuid !== (string) $sender->uuid) {
                $targetUser->notify(DirectMessageReceivedNotification::fromMessage($message, $senderName, $role));
            }
        }

        return redirect()
            ->route('forums.messages.show', $message)
            ->with('status', 'success')
            ->with('message', 'Message dispatched successfully.');
    }

    public function show(Request $request, ForumMessage $message): View
    {
        $user = $request->user();
        abort_if(! $user?->can('forums.view'), 403);

        $userUuid = (string) $user->uuid;

        // Verify the user is either the sender or a recipient
        $isSender = $message->sender_id === $userUuid;
        $recipientRecord = ForumMessageRecipient::where('message_id', $message->id)
            ->where('user_id', $userUuid)
            ->first();

        abort_if(! $isSender && ! $recipientRecord, 403);

        // If user is a recipient, mark as read
        if ($recipientRecord && ! $recipientRecord->read_at) {
            $recipientRecord->update(['read_at' => now()]);
        }

        $message->load([
            'sender:uuid,first_name,last_name,email',
            'recipients.user:uuid,first_name,last_name,email',
        ]);

        $visibleRecipients = $message->visibleRecipientsFor($user);
        $message->setRelation('recipients', $visibleRecipients);

        return view('forums::messages-show', [
            'message' => $message,
            'visibleRecipients' => $visibleRecipients,
            'isSender' => $isSender,
        ]);
    }
}
