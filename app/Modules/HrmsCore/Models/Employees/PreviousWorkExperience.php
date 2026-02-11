<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Employees;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PreviousWorkExperience model - Employee work history before current employment.
 *
 * @property int $id
 * @property string $uuid
 * @property int $employee_id
 * @property string $company_name
 * @property string $position
 * @property \Illuminate\Support\Carbon|null $start_date
 * @property \Illuminate\Support\Carbon|null $end_date
 * @property string|null $responsibilities
 * @property string|null $reason_for_leaving
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class PreviousWorkExperience extends Model
{
    use HasHrmsUuid;
    use UsesTenantConnection;

    protected $table = 'hrms_previous_work_experiences';

    protected $fillable = [
        'employee_id',
        'company_name',
        'position',
        'start_date',
        'end_date',
        'responsibilities',
        'reason_for_leaving',
    ];

    /**
     * Get the employee this work experience belongs to.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the duration of employment in months.
     */
    public function durationInMonths(): ?int
    {
        if ($this->start_date === null) {
            return null;
        }

        $endDate = $this->end_date ?? now();

        return (int) $this->start_date->diffInMonths($endDate);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }
}
