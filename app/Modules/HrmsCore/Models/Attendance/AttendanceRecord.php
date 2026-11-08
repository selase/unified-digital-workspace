<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Attendance;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One clock-in/clock-out session. An open session has no clock_out_at.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $employee_id
 * @property \Illuminate\Support\Carbon $work_date
 * @property \Illuminate\Support\Carbon $clock_in_at
 * @property \Illuminate\Support\Carbon|null $clock_out_at
 * @property string $source
 */
final class AttendanceRecord extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;

    protected $table = 'hrms_attendance_records';

    protected $fillable = ['tenant_id', 'employee_id', 'work_date', 'clock_in_at', 'clock_out_at', 'source', 'note', 'created_by'];

    public function minutesWorked(): int
    {
        $end = $this->clock_out_at ?? now();

        return max(0, (int) $this->clock_in_at->diffInMinutes($end));
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    protected function casts(): array
    {
        return ['work_date' => 'date', 'clock_in_at' => 'datetime', 'clock_out_at' => 'datetime'];
    }
}
