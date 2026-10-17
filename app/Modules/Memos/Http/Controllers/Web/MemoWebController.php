<?php

declare(strict_types=1);

namespace App\Modules\Memos\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Libraries\Helper;
use App\Models\User;
use App\Modules\HrmsCore\Models\Organization\Department;
use App\Modules\HrmsCore\Models\Organization\Directorate;
use App\Modules\HrmsCore\Models\Organization\Unit;
use App\Modules\Memos\Models\Memo;
use App\Modules\Memos\Models\MemoMinute;
use App\Modules\Memos\Models\MemoRecipient;
use App\Notifications\MemoDispatchedNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

final class MemoWebController extends Controller
{
    public function create(Request $request): View
    {
        abort_if(! $request->user()?->can('memos.create'), 403);

        $users = User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('first_name')
            ->get(['id', 'uuid', 'first_name', 'last_name', 'email']);

        $departments = Schema::connection('tenant')->hasTable('hrms_departments')
            ? Department::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            : collect();

        $directorates = Schema::connection('tenant')->hasTable('hrms_directorates')
            ? Directorate::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            : collect();

        $units = Schema::connection('tenant')->hasTable('hrms_units')
            ? Unit::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('memos::create', [
            'users' => $users,
            'departments' => $departments,
            'directorates' => $directorates,
            'units' => $units,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('memos.create'), 403);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'recipients' => ['nullable', 'array'],
            'recipients.*.type' => ['required_with:recipients', 'string', Rule::in([
                MemoRecipient::TYPE_USER,
                MemoRecipient::TYPE_UNIT,
                MemoRecipient::TYPE_DEPARTMENT,
                MemoRecipient::TYPE_DIRECTORATE,
                MemoRecipient::TYPE_TENANT,
            ])],
            'recipients.*.id' => ['nullable', 'string'],
            'recipients.*.role' => ['required_with:recipients', 'string', Rule::in([
                MemoRecipient::ROLE_TO,
                MemoRecipient::ROLE_CC,
                MemoRecipient::ROLE_BCC,
            ])],
            'cc_user_ids' => ['nullable', 'array'],
            'cc_user_ids.*' => ['integer'],
            'bcc_user_ids' => ['nullable', 'array'],
            'bcc_user_ids.*' => ['integer'],
            'action' => ['nullable', 'string', Rule::in(['draft', 'send'])],
        ]);

        $rawRecipients = $validated['recipients'] ?? [];
        foreach ($validated['cc_user_ids'] ?? [] as $ccUserId) {
            $rawRecipients[] = [
                'type' => MemoRecipient::TYPE_USER,
                'id' => (string) $ccUserId,
                'role' => MemoRecipient::ROLE_CC,
            ];
        }
        foreach ($validated['bcc_user_ids'] ?? [] as $bccUserId) {
            $rawRecipients[] = [
                'type' => MemoRecipient::TYPE_USER,
                'id' => (string) $bccUserId,
                'role' => MemoRecipient::ROLE_BCC,
            ];
        }

        $rawRecipients = array_values(array_filter(
            $rawRecipients,
            fn (array $r): bool => ($r['type'] ?? '') === MemoRecipient::TYPE_TENANT || ! empty($r['id'])
        ));

        if ($rawRecipients === []) {
            return back()->withErrors(['recipients' => 'Please select at least one recipient (To, CC, or BCC).'])->withInput();
        }

        $shouldSend = ($validated['action'] ?? 'draft') === 'send';
        $status = $shouldSend ? Memo::STATUS_SENT : Memo::STATUS_DRAFT;

        $memo = Memo::create([
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'sender_id' => (string) $user->uuid,
            'status' => $status,
            'sent_at' => $shouldSend ? now() : null,
            'signed_at' => $shouldSend ? now() : null,
        ]);

        $seenKeys = [];
        foreach ($rawRecipients as $recipientData) {
            $type = (string) $recipientData['type'];
            $role = (string) ($recipientData['role'] ?? MemoRecipient::ROLE_TO);
            $recipientId = $type === MemoRecipient::TYPE_TENANT ? null : (isset($recipientData['id']) ? (string) $recipientData['id'] : null);
            $dedupeKey = "{$type}:{$recipientId}";

            if (isset($seenKeys[$dedupeKey])) {
                continue;
            }
            $seenKeys[$dedupeKey] = true;

            $requiresAck = $role === MemoRecipient::ROLE_TO && $type === MemoRecipient::TYPE_USER;

            MemoRecipient::create([
                'memo_id' => $memo->id,
                'tenant_id' => $memo->tenant_id,
                'recipient_type' => $type,
                'recipient_id' => $recipientId,
                'role' => $role,
                'requires_ack' => $requiresAck,
            ]);
        }

        if ($shouldSend) {
            $this->dispatchNotificationsForMemo($memo, $user);
        }

        return redirect()
            ->route('memos.show', $memo)
            ->with('status', 'success')
            ->with('message', $shouldSend ? 'Memo signed and dispatched successfully.' : 'Memo draft created successfully.');
    }

    public function show(Request $request, Memo $memo): View
    {
        $user = $request->user();
        abort_if(! $user?->can('memos.view'), 403);

        $memo->load([
            'sender:uuid,first_name,last_name,email',
            'recipients.user:id,uuid,first_name,last_name,email',
            'recipients.unit:id,name',
            'recipients.department:id,name',
            'recipients.directorate:id,name',
            'minutes.author:uuid,first_name,last_name,email',
            'actions.assignedTo:uuid,first_name,last_name,email',
        ]);

        $isSender = $memo->sender_id === (string) $user->uuid;
        $userRecipient = $memo->recipients
            ->first(fn (MemoRecipient $r) => $r->recipient_type === MemoRecipient::TYPE_USER && ($r->recipient_id === (string) $user->id || $r->recipient_id === (string) $user->uuid));

        $visibleRecipients = $memo->visibleRecipientsFor($user);
        $memo->setRelation('recipients', $visibleRecipients);

        return view('memos::show', [
            'memo' => $memo,
            'visibleRecipients' => $visibleRecipients,
            'isSender' => $isSender,
            'userRecipient' => $userRecipient,
        ]);
    }

    public function minute(Request $request, Memo $memo): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('memos.minute'), 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        MemoMinute::create([
            'memo_id' => $memo->id,
            'tenant_id' => $memo->tenant_id,
            'author_id' => (string) $user->uuid,
            'body' => $validated['body'],
        ]);

        return redirect()
            ->route('memos.show', $memo)
            ->with('status', 'success')
            ->with('message', 'Minute sheet updated successfully.');
    }

    public function sign(Request $request, Memo $memo): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('memos.sign'), 403);
        abort_if($memo->sender_id !== (string) $user->uuid, 403);

        $request->validate([
            'signature' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg', 'max:2048'],
        ]);

        if ($request->hasFile('signature')) {
            $file = $request->file('signature');
            if ($memo->signature_path) {
                Helper::deleteFile($memo->signature_path, $memo->signature_disk ?: 'tenant');
            }
            $path = Helper::processUploadedFile(
                $request,
                'signature',
                'memo_signature_'.$memo->id,
                'memos/'.$memo->id.'/signatures',
                'tenant'
            );
            $memo->fill([
                'signature_disk' => 'tenant',
                'signature_path' => $path,
                'signature_filename' => $file?->getClientOriginalName(),
                'signature_mime_type' => $file?->getClientMimeType(),
                'signature_size_bytes' => $file?->getSize() ?? 0,
            ]);
        }

        $memo->fill([
            'signed_at' => now(),
            'sent_at' => now(),
            'status' => Memo::STATUS_SENT,
        ]);
        $memo->save();

        $this->dispatchNotificationsForMemo($memo, $user);

        return redirect()
            ->route('memos.show', $memo)
            ->with('status', 'success')
            ->with('message', 'Memo digitally signed and dispatched.');
    }

    public function acknowledge(Request $request, Memo $memo): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('memos.acknowledge'), 403);

        $recipient = $memo->recipients()
            ->where('recipient_type', MemoRecipient::TYPE_USER)
            ->where(function ($q) use ($user): void {
                $q->where('recipient_id', (string) $user->id)
                    ->orWhere('recipient_id', (string) $user->uuid);
            })
            ->first();

        abort_if(! $recipient, 404);

        $recipient->update([
            'acknowledged_at' => now(),
            'acknowledged_by_id' => (string) $user->uuid,
        ]);

        // If all TO user recipients have acknowledged, mark memo as acknowledged
        $hasPendingAcks = $memo->recipients()
            ->where('role', MemoRecipient::ROLE_TO)
            ->where('requires_ack', true)
            ->whereNull('acknowledged_at')
            ->exists();

        if (! $hasPendingAcks) {
            $memo->update(['status' => Memo::STATUS_ACKNOWLEDGED]);
        }

        return redirect()
            ->route('memos.show', $memo)
            ->with('status', 'success')
            ->with('message', 'Receipt of memorandum officially acknowledged.');
    }

    private function dispatchNotificationsForMemo(Memo $memo, User $sender): void
    {
        $senderName = $sender->displayName() ?: (string) $sender->email;
        $recipients = $memo->recipients()->with('user')->get();
        $notifiedUserIds = [$sender->id => true];

        foreach ($recipients as $recipient) {
            if ($recipient->recipient_type === MemoRecipient::TYPE_USER && $recipient->user) {
                $targetUser = $recipient->user;
                if (! isset($notifiedUserIds[$targetUser->id])) {
                    $notifiedUserIds[$targetUser->id] = true;
                    $targetUser->notify(MemoDispatchedNotification::fromMemo($memo, $senderName, (string) $recipient->role));
                }
            }
        }
    }
}
