<?php

declare(strict_types=1);

namespace App\Modules\Beneficiaries\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class BenBeneficiary extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const SERVICE_TYPES = ['food' => 'Food', 'medical' => 'Medical care', 'education' => 'Education support', 'cash' => 'Cash support', 'shelter' => 'Shelter', 'training' => 'Training', 'counselling' => 'Counselling', 'other' => 'Other'];

    protected $table = 'ben_beneficiaries';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** Services and case work are only recorded for people who have agreed and not withdrawn. */
    public function hasValidConsent(): bool
    {
        return $this->consent_given && $this->consent_withdrawn_on === null && $this->anonymised_at === null;
    }

    public function isAnonymised(): bool
    {
        return $this->anonymised_at !== null;
    }

    /** The name, or just initials for staff who may not see personal details. */
    public function displayName(bool $mayViewPersonal): string
    {
        if ($this->isAnonymised() || $mayViewPersonal) {
            return $this->full_name;
        }

        $initials = collect(preg_split('/\s+/', mb_trim($this->full_name)) ?: [])->filter()->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)).'.')->implode(' ');

        return $initials !== '' ? $initials : '—';
    }

    public function age(): ?int
    {
        return $this->date_of_birth?->age;
    }

    /** @return HasMany<BenCase, $this> */
    public function cases(): HasMany
    {
        return $this->hasMany(BenCase::class, 'beneficiary_id');
    }

    /** @return HasMany<BenService, $this> */
    public function services(): HasMany
    {
        return $this->hasMany(BenService::class, 'beneficiary_id');
    }

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date', 'enrolled_on' => 'date', 'exited_on' => 'date', 'consent_given' => 'boolean',
            'consent_recorded_on' => 'date', 'consent_withdrawn_on' => 'date', 'anonymised_at' => 'datetime', 'household_size' => 'integer',
        ];
    }
}
