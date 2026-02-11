<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Recruitment;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Candidate model - Job applicants.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $alternate_phone
 * @property string|null $address
 * @property string|null $city
 * @property string|null $country
 * @property string|null $gender
 * @property \Illuminate\Support\Carbon|null $date_of_birth
 * @property string|null $nationality
 * @property string|null $marital_status
 * @property string|null $current_employer
 * @property string|null $current_position
 * @property string|null $current_salary
 * @property string|null $expected_salary
 * @property string|null $notice_period
 * @property int|null $years_of_experience
 * @property string|null $highest_qualification
 * @property string|null $institution
 * @property string|null $graduation_year
 * @property array<int, string>|null $skills
 * @property array<int, string>|null $languages
 * @property string|null $source
 * @property string|null $referrer_name
 * @property string|null $referrer_email
 * @property string $status
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
final class Candidate extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;
    use SoftDeletes;

    protected $table = 'hrms_candidates';

    protected $fillable = [
        'tenant_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'alternate_phone',
        'address',
        'city',
        'country',
        'gender',
        'date_of_birth',
        'nationality',
        'marital_status',
        'current_employer',
        'current_position',
        'current_salary',
        'expected_salary',
        'notice_period',
        'years_of_experience',
        'highest_qualification',
        'institution',
        'graduation_year',
        'skills',
        'languages',
        'source',
        'referrer_name',
        'referrer_email',
        'status',
        'notes',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * @return HasMany<CandidateApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(CandidateApplication::class);
    }

    /**
     * @return HasMany<CandidateDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(CandidateDocument::class);
    }

    /**
     * @return HasMany<CandidateReference, $this>
     */
    public function references(): HasMany
    {
        return $this->hasMany(CandidateReference::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isBlacklisted(): bool
    {
        return $this->status === 'blacklisted';
    }

    public function isHired(): bool
    {
        return $this->status === 'hired';
    }

    public function blacklist(): void
    {
        $this->status = 'blacklisted';
        $this->save();
    }

    public function markHired(): void
    {
        $this->status = 'hired';
        $this->save();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeWithSkill($query, string $skill)
    {
        return $query->whereJsonContains('skills', $skill);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'current_salary' => 'decimal:2',
            'expected_salary' => 'decimal:2',
            'years_of_experience' => 'integer',
            'skills' => 'array',
            'languages' => 'array',
        ];
    }
}
