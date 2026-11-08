<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Attendance;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A weekly timesheet (week starts Monday).
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $employee_id
 * @property \Illuminate\Support\Carbon $week_start
 * @property string $status
 * @property int $total_minutes
 */
final class Timesheet extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $table = 'hrms_timesheets';

    protected $fillable = ['tenant_id', 'employee_id', 'week_start', 'status', 'total_minutes', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_comment'];

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REJECTED], true);
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /** @return HasMany<TimesheetEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(TimesheetEntry::class, 'timesheet_id')->orderBy('work_date');
    }

    protected function casts(): array
    {
        return ['week_start' => 'date', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'total_minutes' => 'integer'];
    }
}
