<?php

declare(strict_types=1);

namespace App\Modules\Memos\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Libraries\Helper;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\Memos\Http\Requests\MemoAcknowledgeRequest;
use App\Modules\Memos\Http\Requests\MemoActionStoreRequest;
use App\Modules\Memos\Http\Requests\MemoActionUpdateRequest;
use App\Modules\Memos\Http\Requests\MemoConfirmSendRequest;
use App\Modules\Memos\Http\Requests\MemoMinuteStoreRequest;
use App\Modules\Memos\Http\Requests\MemoSendCodeRequest;
use App\Modules\Memos\Http\Requests\MemoShareRequest;
use App\Modules\Memos\Http\Requests\MemoSignatureStoreRequest;
use App\Modules\Memos\Http\Requests\MemoStoreRequest;
use App\Modules\Memos\Http\Requests\MemoUpdateRequest;
use App\Modules\Memos\Http\Resources\MemoActionResource;
use App\Modules\Memos\Http\Resources\MemoMinuteResource;
use App\Modules\Memos\Http\Resources\MemoResource;
use App\Modules\Memos\Models\Memo;
use App\Modules\Memos\Models\MemoAction;
use App\Modules\Memos\Models\MemoMinute;
use App\Modules\Memos\Models\MemoRecipient;
use App\Services\Sms\SmsManager;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

final class MemoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_if(! $request->user()?->can('memos.view'), 403);

        $userId = (string) $request->user()?->id;
        $scope = $this->resolveOrgScope($userId);

        $query = Memo::query()
            ->with($this->memoRelations());

        $query->where(function ($memoQuery) use ($userId, $scope): void {
            $memoQuery->where('sender_id', $userId)
                ->orWhereHas('recipients', function ($recipientQuery) use ($userId, $scope): void {
                    $recipientQuery->where(function ($recipientSubQuery) use ($userId): void {
                        $recipientSubQuery->where('recipient_type', 'user')
                            ->where('recipient_id', $userId);
                    })
                        ->orWhere(function ($recipientSubQuery) use ($scope): void {
                            if (! empty($scope['units'])) {
                                $recipientSubQuery->where('recipient_type', 'unit')
                                    ->whereIn('recipient_id', $scope['units']);
                            }
                        })
                        ->orWhere(function ($recipientSubQuery) use ($scope): void {
                            if (! empty($scope['departments'])) {
                                $recipientSubQuery->where('recipient_type', 'department')
                                    ->whereIn('recipient_id', $scope['departments']);
                            }
                        })
                        ->orWhere(function ($recipientSubQuery) use ($scope): void {
                            if (! empty($scope['directorates'])) {
                                $recipientSubQuery->where('recipient_type', 'directorate')
                                    ->whereIn('recipient_id', $scope['directorates']);
                            }
                        })
                        ->orWhere(function ($recipientSubQuery): void {
                            $recipientSubQuery->where('recipient_type', 'tenant');
                        });
                });
        });

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $memos = $query->latest()->paginate($request->integer('per_page', 15));

        return MemoResource::collection($memos)->response();
    }

    public function store(MemoStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        $memo = Memo::create([
            'subject' => $data['subject'],
            'body' => $data['body'],
            'sender_id' => $request->user()?->id,
            'status' => Memo::STATUS_DRAFT,
        ]);

        $this->syncRecipients($memo, $data['recipients']);

        return (new MemoResource($memo->load($this->memoRelations())))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Memo $memo): MemoResource
    {
        abort_if(! $request->user()?->can('memos.view'), 403);
        $this->ensureVisible($memo, (string) $request->user()?->id);

        return new MemoResource($memo->load($this->memoRelations()));
    }

    public function update(MemoUpdateRequest $request, Memo $memo): MemoResource
    {
        $this->ensureSender($memo, (string) $request->user()?->id);
        $this->ensureEditable($memo);

        $memo->fill($request->validated());
        $memo->save();

        return new MemoResource($memo->load($this->memoRelations()));
    }

    public function destroy(Request $request, Memo $memo): JsonResponse
    {
        abort_if(! $request->user()?->can('memos.delete'), 403);
        $this->ensureSender($memo, (string) $request->user()?->id);
        $this->ensureEditable($memo);

        $memo->delete();

        return response()->json([], 204);
    }

    public function storeSignature(MemoSignatureStoreRequest $request, Memo $memo): MemoResource
    {
        $this->ensureSender($memo, (string) $request->user()?->id);
        $this->ensureEditable($memo);

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
            'signed_at' => now(),
        ]);

        $memo->save();

        return new MemoResource($memo->load($this->memoRelations()));
    }

    public function sendVerificationCode(MemoSendCodeRequest $request, Memo $memo, SmsManager $smsManager): JsonResponse
    {
        $this->ensureSender($memo, (string) $request->user()?->id);
        $this->ensureEditable($memo);

        if (! $memo->signature_path) {
            return response()->json(['message' => 'Signature is required before sending.'], 422);
        }

        if ($memo->recipients()->count() === 0) {
            return response()->json(['message' => 'At least one recipient is required.'], 422);
        }

        $user = $request->user();
        $phone = (string) $user?->phone_no;

        if ($phone === '') {
            return response()->json(['message' => 'Sender phone number is required.'], 422);
        }

        $code = app()->environment('testing')
            ? '123456'
            : (string) random_int(100000, 999999);

        $memo->fill([
            'status' => Memo::STATUS_PENDING,
            'verification_code_hash' => Hash::make($code),
            'verification_sent_at' => now(),
            'verification_expires_at' => now()->addMinutes(10),
            'verification_attempts' => 0,
        ]);
        $memo->save();

        $tenant = app(TenantContext::class)->getTenant();
        if (! $tenant) {
            return response()->json(['message' => 'Tenant context missing.'], 500);
        }

        $smsManager->send($tenant, $phone, "Your memo verification code is {$code}.");

        return response()->json([
            'message' => 'Verification code sent.',
        ]);
    }

    public function confirmSend(MemoConfirmSendRequest $request, Memo $memo): JsonResponse|MemoResource
    {
        $this->ensureSender($memo, (string) $request->user()?->id);

        if ($memo->status !== Memo::STATUS_PENDING) {
            return new MemoResource($memo->load($this->memoRelations()));
        }

        if (! $memo->verification_code_hash || ! $memo->verification_expires_at) {
            return response()->json(['message' => 'Verification code missing.'], 422);
        }

        if ($memo->verification_expires_at->isPast()) {
            return response()->json(['message' => 'Verification code expired.'], 422);
        }

        if ($memo->verification_attempts >= 5) {
            return response()->json(['message' => 'Too many verification attempts.'], 429);
        }

        $code = $request->input('code');

        if (! Hash::check($code, (string) $memo->verification_code_hash)) {
            $memo->increment('verification_attempts');

            return response()->json(['message' => 'Invalid verification code.'], 422);
        }

        $memo->fill([
            'status' => Memo::STATUS_SENT,
            'verified_at' => now(),
            'sent_at' => now(),
            'verification_code_hash' => null,
        ]);
        $memo->save();

        return new MemoResource($memo->load($this->memoRelations()));
    }

    public function acknowledge(MemoAcknowledgeRequest $request, Memo $memo): JsonResponse|MemoResource
    {
        $userId = (string) $request->user()?->id;
        $this->ensureVisible($memo, $userId);

        $recipient = $memo->recipients()
            ->where('recipient_type', 'user')
            ->where('recipient_id', $userId)
            ->first();

        if (! $recipient || ! $recipient->requires_ack) {
            return response()->json(['message' => 'Acknowledgement not required.'], 422);
        }

        $recipient->fill([
            'acknowledged_at' => now(),
            'acknowledged_by_id' => $userId,
        ]);
        $recipient->save();

        $remaining = $memo->recipients()->where('requires_ack', true)->whereNull('acknowledged_at')->count();
        if ($remaining === 0 && $memo->status === Memo::STATUS_SENT) {
            $memo->status = Memo::STATUS_ACKNOWLEDGED;
            $memo->save();
        }

        return new MemoResource($memo->load($this->memoRelations()));
    }

    public function storeMinute(MemoMinuteStoreRequest $request, Memo $memo): JsonResponse
    {
        $userId = (string) $request->user()?->id;
        $this->ensureVisible($memo, $userId);

        $minute = MemoMinute::create([
            'memo_id' => $memo->id,
            'tenant_id' => $memo->tenant_id,
            'author_id' => $userId,
            'body' => $request->validated()['body'],
        ]);

        return (new MemoMinuteResource($minute))
            ->response()
            ->setStatusCode(201);
    }

    public function share(MemoShareRequest $request, Memo $memo): MemoResource
    {
        $userId = (string) $request->user()?->id;
        $this->ensureVisible($memo, $userId);

        $this->syncRecipients($memo, $request->validated()['recipients'], $userId);

        return new MemoResource($memo->load($this->memoRelations()));
    }

    public function storeAction(MemoActionStoreRequest $request, Memo $memo): JsonResponse
    {
        $userId = (string) $request->user()?->id;
        $this->ensureVisible($memo, $userId);

        $data = $request->validated();

        $action = MemoAction::create([
            'memo_id' => $memo->id,
            'tenant_id' => $memo->tenant_id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'assigned_to_id' => $data['assigned_to_id'] ?? null,
            'due_at' => $data['due_at'] ?? null,
            'status' => 'open',
        ]);

        return (new MemoActionResource($action))
            ->response()
            ->setStatusCode(201);
    }

    public function updateAction(MemoActionUpdateRequest $request, Memo $memo, MemoAction $action): MemoActionResource
    {
        $userId = (string) $request->user()?->id;
        $this->ensureVisible($memo, $userId);

        if ($action->memo_id !== $memo->id) {
            abort(404);
        }

        $action->fill($request->validated());
        $action->save();

        return new MemoActionResource($action);
    }

    /**
     * @param  array<int, array{type: string, id?: string, role?: string}>  $recipients
     */
    private function syncRecipients(Memo $memo, array $recipients, ?string $sharedById = null): void
    {
        foreach ($recipients as $recipient) {
            $type = $recipient['type'];
            $role = $recipient['role'] ?? MemoRecipient::ROLE_TO;
            $recipientId = $type === MemoRecipient::TYPE_TENANT ? null : ($recipient['id'] ?? null);
            $requiresAck = $role === MemoRecipient::ROLE_TO && $type === MemoRecipient::TYPE_USER;

            MemoRecipient::query()->updateOrCreate([
                'memo_id' => $memo->id,
                'recipient_type' => $type,
                'recipient_id' => $recipientId,
                'role' => $role,
            ], [
                'tenant_id' => $memo->tenant_id,
                'requires_ack' => $requiresAck,
                'shared_by_id' => $sharedById,
                'shared_at' => $sharedById ? now() : null,
            ]);
        }
    }

    private function ensureVisible(Memo $memo, string $userId): void
    {
        if ($memo->sender_id === $userId) {
            return;
        }

        $scope = $this->resolveOrgScope($userId);

        $isRecipient = $memo->recipients()
            ->where(function ($recipientQuery) use ($userId, $scope): void {
                $recipientQuery->where(function ($recipientSubQuery) use ($userId): void {
                    $recipientSubQuery->where('recipient_type', 'user')
                        ->where('recipient_id', $userId);
                })
                    ->orWhere(function ($recipientSubQuery) use ($scope): void {
                        if (! empty($scope['units'])) {
                            $recipientSubQuery->where('recipient_type', 'unit')
                                ->whereIn('recipient_id', $scope['units']);
                        }
                    })
                    ->orWhere(function ($recipientSubQuery) use ($scope): void {
                        if (! empty($scope['departments'])) {
                            $recipientSubQuery->where('recipient_type', 'department')
                                ->whereIn('recipient_id', $scope['departments']);
                        }
                    })
                    ->orWhere(function ($recipientSubQuery) use ($scope): void {
                        if (! empty($scope['directorates'])) {
                            $recipientSubQuery->where('recipient_type', 'directorate')
                                ->whereIn('recipient_id', $scope['directorates']);
                        }
                    })
                    ->orWhere(function ($recipientSubQuery): void {
                        $recipientSubQuery->where('recipient_type', 'tenant');
                    });
            })
            ->exists();

        abort_if(! $isRecipient, 403);
    }

    private function ensureSender(Memo $memo, string $userId): void
    {
        abort_if($memo->sender_id !== $userId, 403);
    }

    private function ensureEditable(Memo $memo): void
    {
        abort_if(! in_array($memo->status, [Memo::STATUS_DRAFT, Memo::STATUS_PENDING], true), 422);
    }

    /**
     * @return array{departments: array<int|string>, directorates: array<int|string>, units: array<int|string>}
     */
    private function resolveOrgScope(string $userId): array
    {
        $departments = [];
        $directorates = [];
        $units = [];

        $tenantDriver = config('database.connections.tenant.driver');
        if ($tenantDriver === null) {
            return [
                'departments' => $departments,
                'directorates' => $directorates,
                'units' => $units,
            ];
        }

        $tenantConnection = config('database.default_tenant_connection', 'tenant');
        if (! Schema::connection($tenantConnection)->hasTable('employees')) {
            return [
                'departments' => $departments,
                'directorates' => $directorates,
                'units' => $units,
            ];
        }

        $employee = Employee::query()
            ->where('user_id', $userId)
            ->first();

        if ($employee) {
            $departments = $employee->departments()->pluck('id')->filter()->values()->all();
            $directorates = $employee->directorates()->pluck('id')->filter()->values()->all();
            $units = $employee->units()->pluck('id')->filter()->values()->all();
        }

        return [
            'departments' => $departments,
            'directorates' => $directorates,
            'units' => $units,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function memoRelations(): array
    {
        $relations = [
            'recipients.user',
            'minutes',
            'actions',
        ];

        $tenantConnection = config('database.default_tenant_connection', 'tenant');

        if (Schema::connection($tenantConnection)->hasTable('hrms_units')) {
            $relations[] = 'recipients.unit';
        }

        if (Schema::connection($tenantConnection)->hasTable('hrms_departments')) {
            $relations[] = 'recipients.department';
        }

        if (Schema::connection($tenantConnection)->hasTable('hrms_directorates')) {
            $relations[] = 'recipients.directorate';
        }

        return $relations;
    }
}
