<?php

declare(strict_types=1);

namespace App\Modules\Beneficiaries\Services;

use App\Modules\Beneficiaries\Models\BenAccessLog;
use App\Modules\Beneficiaries\Models\BenBeneficiary;
use App\Modules\Beneficiaries\Models\BenCase;
use App\Modules\Beneficiaries\Models\BenCaseNote;
use App\Modules\Beneficiaries\Models\BenService;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

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
