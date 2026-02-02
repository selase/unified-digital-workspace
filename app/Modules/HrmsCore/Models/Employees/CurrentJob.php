<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Employees;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CurrentJob model - Employee job assignments.
 *
 * @property int $id
 * @property string $uuid
 * @property int $employee_id
 * @property string $job_title
 * @property \Illuminate\Support\Carbon|null $start_date
 * @property \Illuminate\Support\Carbon|null $end_date
 * @property int|null $supervisor_id
 * @property string|null $description
 * @property bool $is_current
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class CurrentJob extends Model
{
    use HasHrmsUuid;

    protected $table = 'hrms_current_jobs';

    protected $connection = 'landlord';

    protected $fillable = [
        'employee_id',
        'job_title',
        'start_date',
        'end_date',
        'supervisor_id',
        'description',
        'is_current',
    ];

    /**
     * Get the employee this job belongs to.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the supervisor for this job.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
        ];
    }
}
