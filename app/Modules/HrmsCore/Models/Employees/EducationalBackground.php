<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Employees;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * EducationalBackground model - Employee educational history.
 *
 * @property int $id
 * @property string $uuid
 * @property int $employee_id
 * @property string $institution_name
 * @property string $qualification
 * @property string|null $field_of_study
 * @property \Illuminate\Support\Carbon|null $start_date
 * @property \Illuminate\Support\Carbon|null $end_date
 * @property string|null $grade
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class EducationalBackground extends Model
{
    use HasHrmsUuid;
    use UsesTenantConnection;

    protected $table = 'hrms_educational_backgrounds';

    protected $fillable = [
        'employee_id',
        'institution_name',
        'qualification',
        'field_of_study',
        'start_date',
        'end_date',
        'grade',
    ];

    /**
     * Get the employee this educational background belongs to.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
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
