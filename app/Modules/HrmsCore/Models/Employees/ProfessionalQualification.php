<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Employees;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ProfessionalQualification model - Employee professional certifications.
 *
 * @property int $id
 * @property string $uuid
 * @property int $employee_id
 * @property string $certification_name
 * @property string $issuing_body
 * @property \Illuminate\Support\Carbon|null $date_obtained
 * @property \Illuminate\Support\Carbon|null $expiry_date
 * @property string|null $certificate_number
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class ProfessionalQualification extends Model
{
    use HasHrmsUuid;
    use UsesTenantConnection;

    protected $table = 'hrms_professional_qualifications';

    protected $fillable = [
        'employee_id',
        'certification_name',
        'issuing_body',
        'date_obtained',
        'expiry_date',
        'certificate_number',
    ];

    /**
     * Get the employee this qualification belongs to.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Check if the qualification has expired.
     */
    public function isExpired(): bool
    {
        if ($this->expiry_date === null) {
            return false;
        }

        return $this->expiry_date->isPast();
    }

    /**
     * Scope to only active (non-expired) qualifications.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expiry_date')
                ->orWhere('expiry_date', '>', now());
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_obtained' => 'date',
            'expiry_date' => 'date',
        ];
    }
}
