<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Recruitment;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * InterviewPanel model - Interviewers for each interview.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $interview_id
 * @property int $employee_id
 * @property string $role
 * @property bool $is_mandatory
 * @property string $attendance_status
 * @property \Illuminate\Support\Carbon|null $confirmed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class InterviewPanel extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;

    protected $table = 'hrms_interview_panels';

    protected $connection = 'landlord';

    public const ROLE_LEAD = 'lead';

    public const ROLE_INTERVIEWER = 'interviewer';

    public const ROLE_OBSERVER = 'observer';

    protected $fillable = [
        'tenant_id',
        'interview_id',
        'employee_id',
        'role',
        'is_mandatory',
        'attendance_status',
        'confirmed_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'interviewer',
        'is_mandatory' => true,
        'attendance_status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_mandatory' => 'boolean',
            'confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Interview, $this>
     */
    public function interview(): BelongsTo
    {
        return $this->belongsTo(Interview::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function isLead(): bool
    {
        return $this->role === self::ROLE_LEAD;
    }

    public function isObserver(): bool
    {
        return $this->role === self::ROLE_OBSERVER;
    }

    public function confirm(): void
    {
        $this->attendance_status = 'confirmed';
        $this->confirmed_at = \Illuminate\Support\Carbon::now();
        $this->save();
    }

    public function markAttended(): void
    {
        $this->attendance_status = 'attended';
        $this->save();
    }

    public function markAbsent(): void
    {
        $this->attendance_status = 'absent';
        $this->save();
    }

    /**
     * @return array<string, string>
     */
    public static function roles(): array
    {
        return [
            self::ROLE_LEAD => 'Lead Interviewer',
            self::ROLE_INTERVIEWER => 'Interviewer',
            self::ROLE_OBSERVER => 'Observer',
        ];
    }
}
