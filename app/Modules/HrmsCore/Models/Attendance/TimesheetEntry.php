<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Attendance;

use Illuminate\Database\Eloquent\Model;

final class TimesheetEntry extends Model
{
    public $timestamps = false;

    protected $table = 'hrms_timesheet_entries';

    protected $connection = 'tenant';

    protected $fillable = ['timesheet_id', 'work_date', 'minutes', 'project', 'description'];

    protected function casts(): array
    {
        return ['work_date' => 'date', 'minutes' => 'integer'];
    }
}
