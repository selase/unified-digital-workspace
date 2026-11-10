<?php

declare(strict_types=1);

namespace App\Modules\Beneficiaries\Services;

use App\Modules\Beneficiaries\Models\BenAccessLog;
use App\Modules\Beneficiaries\Models\BenBeneficiary;
use App\Modules\Beneficiaries\Models\BenCase;
use App\Modules\Beneficiaries\Models\BenCaseNote;
use App\Modules\Beneficiaries\Models\BenService;
use App\Modules\Beneficiaries\Models\BenDocument;
use App\Modules\Beneficiaries\Models\BenReferral;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Beneficiary records are personal and often sensitive, so the rules here are strict: nothing is done for a
 * person without recorded consent, confidential notes are flagged, every access is logged, and personal details
 * can be erased on request while keeping anonymous counts.
 */
final class BeneficiaryService
{
    public function log(string $tenantId, ?string $userUuid, string $action, ?BenBeneficiary $beneficiary = null, ?string $detail = null): void
    {
        BenAccessLog::query()->create([
            'tenant_id' => $tenantId,
            'user_uuid' => $userUuid,
            'beneficiary_id' => $beneficiary?->id,
            'action' => $action,
            'detail' => $detail,
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array{date_of_birth?: string|null, gender?: string|null, phone?: string|null, community?: string|null, region?: string|null, household_size?: int|null, programme?: string|null, enrolled_on?: string|null, notes?: string|null}  $details
     */
    public function register(string $tenantId, string $fullName, array $details, ?string $userUuid): BenBeneficiary
    {
        return DB::connection('tenant')->transaction(function () use ($tenantId, $fullName, $details, $userUuid): BenBeneficiary {
            $beneficiary = BenBeneficiary::query()->create([
                ...$details,
                'tenant_id' => $tenantId,
                'full_name' => $fullName,
                'status' => 'active',
                'consent_given' => false,
                'enrolled_on' => $details['enrolled_on'] ?? today()->toDateString(),
            ]);
            $beneficiary->forceFill(['reference' => 'BEN-'.mb_str_pad((string) $beneficiary->id, 5, '0', STR_PAD_LEFT)])->save();
            $this->log($tenantId, $userUuid, 'registered', $beneficiary);

            return $beneficiary;
        });
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public function update(BenBeneficiary $beneficiary, array $details, ?string $userUuid): BenBeneficiary
    {
        if ($beneficiary->isAnonymised()) {
            throw new DomainException('An anonymised record cannot be edited.');
        }

        $beneficiary->update($details);
        $this->log($beneficiary->tenant_id, $userUuid, 'updated', $beneficiary);

        return $beneficiary;
    }

    public function recordConsent(BenBeneficiary $beneficiary, string $scope, CarbonInterface $date, ?string $userUuid): BenBeneficiary
    {
        if ($beneficiary->isAnonymised()) {
            throw new DomainException('An anonymised record cannot be edited.');
        }
        if (mb_trim($scope) === '') {
            throw new DomainException('Say what the person has agreed to.');
        }

        $beneficiary->forceFill([
            'consent_given' => true,
            'consent_scope' => $scope,
            'consent_recorded_on' => $date->toDateString(),
            'consent_recorded_by' => $userUuid,
            'consent_withdrawn_on' => null,
        ])->save();
        $this->log($beneficiary->tenant_id, $userUuid, 'consent_recorded', $beneficiary, $scope);

        return $beneficiary;
    }

    public function withdrawConsent(BenBeneficiary $beneficiary, ?string $userUuid): BenBeneficiary
    {
        if (! $beneficiary->hasValidConsent()) {
            throw new DomainException('There is no valid consent to withdraw.');
        }

        $beneficiary->forceFill(['consent_withdrawn_on' => today()])->save();
        $this->log($beneficiary->tenant_id, $userUuid, 'consent_withdrawn', $beneficiary);

        return $beneficiary;
    }

    public function exit(BenBeneficiary $beneficiary, string $reason, ?string $userUuid): BenBeneficiary
    {
        if ($beneficiary->status === 'exited') {
            throw new DomainException('This person has already left the programme.');
        }

        $beneficiary->forceFill(['status' => 'exited', 'exited_on' => today(), 'exit_reason' => $reason])->save();
        $beneficiary->cases()->where('status', 'open')->update(['status' => 'closed', 'closed_on' => today(), 'outcome' => 'Closed on exit: '.$reason]);
        $this->log($beneficiary->tenant_id, $userUuid, 'exited', $beneficiary, $reason);

        return $beneficiary;
    }

    /**
     * Erases everything that identifies the person (name, birth date, phone, notes, case notes) and keeps the
     * counts: programme, region, gender, services delivered. This cannot be undone.
     */
    public function anonymise(BenBeneficiary $beneficiary, ?string $userUuid): BenBeneficiary
    {
        if ($beneficiary->isAnonymised()) {
            throw new DomainException('This record is already anonymised.');
        }

        return DB::connection('tenant')->transaction(function () use ($beneficiary, $userUuid): BenBeneficiary {
            $caseIds = $beneficiary->cases()->pluck('id');
            BenCaseNote::query()->whereIn('case_id', $caseIds)->delete();
            $beneficiary->cases()->update(['title' => 'Case (anonymised)', 'outcome' => null, 'assigned_to' => null]);
            $beneficiary->services()->update(['description' => null]);
            foreach ($beneficiary->documents()->get() as $document) {
                Storage::disk('tenant')->delete($document->path);
            }
            $beneficiary->documents()->delete();
            $beneficiary->referrals()->update(['reason' => null, 'outcome' => null, 'referred_to' => 'Anonymised']);

            $beneficiary->forceFill([
                'full_name' => 'Anonymised',
                'date_of_birth' => null,
                'phone' => null,
                'community' => null,
                'notes' => null,
                'consent_scope' => null,
                'anonymised_at' => now(),
            ])->save();

            $this->log($beneficiary->tenant_id, $userUuid, 'anonymised', $beneficiary);

            return $beneficiary;
        });
    }

    public function openCase(BenBeneficiary $beneficiary, string $title, string $category, string $priority, ?string $userUuid): BenCase
    {
        $this->assertConsent($beneficiary);

        $case = BenCase::query()->create([
            'tenant_id' => $beneficiary->tenant_id,
            'beneficiary_id' => $beneficiary->id,
            'title' => $title,
            'category' => $category,
            'priority' => $priority,
            'status' => 'open',
            'opened_on' => today(),
            'assigned_to' => $userUuid,
        ]);
        $this->log($beneficiary->tenant_id, $userUuid, 'case_opened', $beneficiary, $title);

        return $case;
    }

    public function closeCase(BenCase $case, string $outcome, ?string $userUuid): BenCase
    {
        if ($case->status === 'closed') {
            throw new DomainException('This case is already closed.');
        }

        $case->forceFill(['status' => 'closed', 'closed_on' => today(), 'outcome' => $outcome])->save();
        $this->log($case->tenant_id, $userUuid, 'case_closed', $case->beneficiary()->first(), $case->title);

        return $case;
    }

    public function addNote(BenCase $case, string $note, bool $confidential, ?string $userUuid): BenCaseNote
    {
        $beneficiary = $case->beneficiary()->firstOrFail();
        $this->assertConsent($beneficiary);
        if ($case->status === 'closed') {
            throw new DomainException('Reopen the case before adding notes.');
        }

        $entry = BenCaseNote::query()->create(['case_id' => $case->id, 'author_uuid' => $userUuid, 'note' => $note, 'is_confidential' => $confidential]);
        $this->log($case->tenant_id, $userUuid, 'note_added', $beneficiary, $confidential ? 'confidential' : null);

        return $entry;
    }

    public function recordService(BenBeneficiary $beneficiary, CarbonInterface $date, string $type, ?string $description, ?int $value, ?string $userUuid): BenService
    {
        $this->assertConsent($beneficiary);
        if (! array_key_exists($type, BenBeneficiary::SERVICE_TYPES)) {
            throw new DomainException('Choose the kind of service.');
        }
        if ($date->gt(today())) {
            throw new DomainException('A service cannot be dated in the future.');
        }
        if ($beneficiary->status !== 'active') {
            throw new DomainException('Services can only be recorded for people in the programme.');
        }

        $service = BenService::query()->create([
            'tenant_id' => $beneficiary->tenant_id,
            'beneficiary_id' => $beneficiary->id,
            'delivered_on' => $date->toDateString(),
            'type' => $type,
            'description' => $description,
            'value' => $value,
            'delivered_by' => $userUuid,
        ]);
        $this->log($beneficiary->tenant_id, $userUuid, 'service_recorded', $beneficiary, $type);

        return $service;
    }

    public const MAX_DOCUMENT_KB = 5120;

    /** @var list<string> */
    private const DOCUMENT_TYPES = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

    /** Keeps a photo or paper with the person, on the organisation's private storage. Needs consent, like all other work. */
    public function attachDocument(BenBeneficiary $beneficiary, string $kind, UploadedFile $file, ?string $userUuid): BenDocument
    {
        $this->assertConsent($beneficiary);
        if (! array_key_exists($kind, BenDocument::KINDS)) {
            throw new DomainException('Choose what kind of document this is.');
        }

        $extension = mb_strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, self::DOCUMENT_TYPES, true) || $file->getSize() > self::MAX_DOCUMENT_KB * 1024) {
            throw new DomainException('Attach a PDF or image (JPG, PNG, WebP) of 5 MB or less.');
        }
        if ($kind === 'photo' && $extension === 'pdf') {
            throw new DomainException('A photo must be an image.');
        }

        $path = Storage::disk('tenant')->putFileAs('beneficiaries/'.$beneficiary->tenant_id, $file, Str::uuid().'.'.$extension);
        $document = BenDocument::query()->create([
            'tenant_id' => $beneficiary->tenant_id,
            'beneficiary_id' => $beneficiary->id,
            'kind' => $kind,
            'name' => mb_substr(basename($file->getClientOriginalName()), 0, 200),
            'path' => (string) $path,
            'mime' => $extension === 'pdf' ? 'application/pdf' : 'image/'.($extension === 'jpg' ? 'jpeg' : $extension),
            'size' => (int) $file->getSize(),
            'uploaded_by' => $userUuid,
        ]);
        $this->log($beneficiary->tenant_id, $userUuid, 'document_added', $beneficiary, $kind);

        return $document;
    }

    public function removeDocument(BenDocument $document, ?string $userUuid): void
    {
        Storage::disk('tenant')->delete($document->path);
        $this->log($document->tenant_id, $userUuid, 'document_removed', $document->beneficiary()->first(), $document->kind);
        $document->delete();
    }

    public function refer(BenBeneficiary $beneficiary, string $referredTo, ?string $reason, ?BenCase $case, ?CarbonInterface $followUpOn, ?string $userUuid): BenReferral
    {
        $this->assertConsent($beneficiary);
        if (mb_trim($referredTo) === '') {
            throw new DomainException('Say who the person is being referred to.');
        }
        if ($case instanceof BenCase && $case->beneficiary_id !== $beneficiary->id) {
            throw new DomainException('That case belongs to someone else.');
        }
        if ($followUpOn instanceof CarbonInterface && $followUpOn->lt(today())) {
            throw new DomainException('The follow-up date cannot be in the past.');
        }

        $referral = BenReferral::query()->create([
            'tenant_id' => $beneficiary->tenant_id,
            'beneficiary_id' => $beneficiary->id,
            'case_id' => $case?->id,
            'referred_to' => $referredTo,
            'reason' => $reason,
            'referred_on' => today(),
            'follow_up_on' => $followUpOn?->toDateString(),
            'status' => 'made',
            'created_by' => $userUuid,
        ]);
        $this->log($beneficiary->tenant_id, $userUuid, 'referral_made', $beneficiary, $referredTo);

        return $referral;
    }

    public function updateReferral(BenReferral $referral, string $status, ?string $outcome, ?string $userUuid): BenReferral
    {
        if (! array_key_exists($status, BenReferral::STATUSES) || $status === 'made') {
            throw new DomainException('Choose accepted, completed or declined.');
        }
        if (! $referral->isOpen()) {
            throw new DomainException('This referral is already finished.');
        }

        $referral->forceFill(['status' => $status, 'outcome' => $outcome])->save();
        $this->log($referral->tenant_id, $userUuid, 'referral_'.$status, $referral->beneficiary()->first(), $referral->referred_to);

        return $referral;
    }

    /**
     * Counts that contain no personal details.
     *
     * @return array{total: int, active: int, with_consent: int, reached_this_year: int, by_programme: array<string, int>, by_gender: array<string, int>, by_region: array<string, int>, services_by_type: array<string, int>}
     */
    public function summary(string $tenantId, CarbonInterface $since): array
    {
        $base = BenBeneficiary::query()->where('tenant_id', $tenantId);
        $count = fn (string $column): array => (clone $base)->whereNotNull($column)->selectRaw("{$column} as label, COUNT(*) as total")->groupBy($column)->orderByDesc('total')->pluck('total', 'label')->map(fn ($value): int => (int) $value)->all();

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', 'active')->count(),
            'with_consent' => (clone $base)->where('consent_given', true)->whereNull('consent_withdrawn_on')->count(),
            'reached_this_year' => BenService::query()->where('tenant_id', $tenantId)->whereDate('delivered_on', '>=', $since->toDateString())->distinct()->count('beneficiary_id'),
            'by_programme' => $count('programme'),
            'by_gender' => $count('gender'),
            'by_region' => $count('region'),
            'services_by_type' => BenService::query()->where('tenant_id', $tenantId)->whereDate('delivered_on', '>=', $since->toDateString())->selectRaw('type, COUNT(*) as total')->groupBy('type')->orderByDesc('total')->pluck('total', 'type')->map(fn ($value): int => (int) $value)->all(),
        ];
    }

    private function assertConsent(BenBeneficiary $beneficiary): void
    {
        if (! $beneficiary->hasValidConsent()) {
            throw new DomainException('Record the person\'s consent before doing any case work or recording services for them.');
        }
    }
}
