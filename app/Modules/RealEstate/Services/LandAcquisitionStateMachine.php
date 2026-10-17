<?php

declare(strict_types=1);

namespace App\Modules\RealEstate\Services;

use App\Modules\RealEstate\Models\LandParcel;
use DomainException;

final class LandAcquisitionStateMachine
{
    public const STAGE_DUE_DILIGENCE = LandParcel::STAGE_DUE_DILIGENCE;

    public const STAGE_REGISTRATION = LandParcel::STAGE_REGISTRATION;

    public const STAGE_PERMITTING = LandParcel::STAGE_PERMITTING;

    public const STAGE_READY_TO_BUILD = LandParcel::STAGE_READY_TO_BUILD;

    public function transitionTo(LandParcel $parcel, string $targetStage, string $actorUuid): LandParcel
    {
        $current = $parcel->current_stage;

        if ($targetStage === self::STAGE_REGISTRATION && $current === self::STAGE_DUE_DILIGENCE) {
            $this->validateDueDiligencePhase($parcel);
            $parcel->update([
                'current_stage' => self::STAGE_REGISTRATION,
                'registration_verified_by_id' => $actorUuid,
            ]);
        } elseif ($targetStage === self::STAGE_PERMITTING && $current === self::STAGE_REGISTRATION) {
            $this->validateRegistrationPhase($parcel);
            $parcel->update([
                'current_stage' => self::STAGE_PERMITTING,
                'permit_verified_by_id' => $actorUuid,
            ]);
        } elseif ($targetStage === self::STAGE_READY_TO_BUILD && $current === self::STAGE_PERMITTING) {
            $this->validatePermittingPhase($parcel);
            $parcel->update([
                'current_stage' => self::STAGE_READY_TO_BUILD,
            ]);
        } else {
            throw new DomainException("Invalid workflow state transition from [{$current}] to [{$targetStage}]. Regulatory lifecycle enforces linear progression.");
        }

        return $parcel->fresh() ?? $parcel;
    }

    private function validateDueDiligencePhase(LandParcel $parcel): void
    {
        if ($parcel->is_legacy) {
            return;
        }

        if (! $parcel->is_due_diligence_verified) {
            throw new DomainException('Cannot advance: Lands Commission search details and site inspection are unverified.');
        }

        if (! $parcel->search_issue_date) {
            throw new DomainException('Cannot advance: Lands Commission search issue date is required.');
        }

        if ($parcel->search_issue_date->diffInDays(now()) > 30) {
            throw new DomainException('Lands Commission search report is older than 30 days and requires re-validation.');
        }

        if (in_array($parcel->tenure_type, ['STOOL', 'FAMILY'], true) && ! $parcel->has_traditional_consent_letter) {
            throw new DomainException('Customary Lands Secretariat or Traditional Authority Consent Letter is required for Stool/Family land profiles.');
        }

        if ($parcel->tenure_type === 'STATE' && ! $parcel->has_state_allocation_note) {
            throw new DomainException('Lands Commission State Allocation Note is required for State land profiles.');
        }
    }

    private function validateRegistrationPhase(LandParcel $parcel): void
    {
        if (empty($parcel->indenture_reference) || empty($parcel->land_title_certificate_number)) {
            throw new DomainException('Deeds execution and formal Land Title Certificate registration records are incomplete.');
        }

        if (! $parcel->stamp_duty_paid) {
            throw new DomainException('Lands Commission stamp duty receipt must be marked paid before building permits can be submitted.');
        }
    }

    private function validatePermittingPhase(LandParcel $parcel): void
    {
        $giaNumber = (string) $parcel->architect_gia_number;
        $ghieNumber = (string) $parcel->structural_engineer_ghie_number;

        if (! preg_match('/^GIA-[A-Z0-9\-\/]{3,20}$/i', $giaNumber)) {
            throw new DomainException('Invalid Architect registration format. Must be an official Ghana Institute of Architects identifier (e.g. GIA-2024/09).');
        }

        if (! preg_match('/^GhIE-[A-Z0-9\-\/]{3,20}$/i', $ghieNumber)) {
            throw new DomainException('Invalid Structural Engineer registration format. Must be an official Ghana Institution of Engineering identifier (e.g. GhIE-STR-441).');
        }

        if (empty($parcel->mmda_permit_number) || ! $parcel->is_permit_active) {
            throw new DomainException('Metropolitan/District Assembly (MMDA) construction permit approval is missing or inactive.');
        }

        if ($parcel->requires_epa_clearance && empty($parcel->epa_clearance_number)) {
            throw new DomainException('Environmental Protection Agency (EPA) clearance certificate number is required for this zonal classification.');
        }
    }
}
