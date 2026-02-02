<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Employees;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * EmploymentStatus model - Employee employment status history.
 *
 * @property int $id
 * @property string $uuid
 * @property int $employee_id
 * @property string $status
 * @property \Illuminate\Support\Carbon $effective_date
 * @property \Illuminate\Support\Carbon|null $end_date
 * @property string|null $reason
 * @property bool $is_current
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class EmploymentStatus extends Model
{
    use HasHrmsUuid;

    /**
     * Employment status constants.
     */
    public const STATUS_ACTIVE = 'active';

    public const STATUS_ON_LEAVE = 'on_leave';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_TERMINATED = 'terminated';

    public const STATUS_RESIGNED = 'resigned';

    public const STATUS_RETIRED = 'retired';

    protected $table = 'hrms_employment_statuses';

    protected $connection = 'landlord';

    protected $fillable = [
        'employee_id',
        'status',
        'effective_date',
        'end_date',
        'reason',
        'is_current',
    ];

    /**
     * Get all available status options.
     *
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_ON_LEAVE => 'On Leave',
            self::STATUS_SUSPENDED => 'Suspended',
            self::STATUS_TERMINATED => 'Terminated',
            self::STATUS_RESIGNED => 'Resigned',
            self::STATUS_RETIRED => 'Retired',
        ];
    }

    /**
     * Get the employee this status belongs to.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Scope to only current statuses.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
        ];
    }
}
