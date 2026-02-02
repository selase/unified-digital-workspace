<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Recruitment;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * OnboardingTask model - Tasks for new hires.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $employee_id
 * @property int|null $application_id
 * @property string $name
 * @property string|null $description
 * @property string|null $category
 * @property int $sequence
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $due_date
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property int|null $assigned_to
 * @property int|null $completed_by
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class OnboardingTask extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;

    protected $table = 'hrms_onboarding_tasks';

    protected $connection = 'landlord';

    public const CATEGORY_DOCUMENTATION = 'documentation';

    public const CATEGORY_TRAINING = 'training';

    public const CATEGORY_EQUIPMENT = 'equipment';

    public const CATEGORY_ACCESS = 'access';

    public const CATEGORY_ORIENTATION = 'orientation';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'application_id',
        'name',
        'description',
        'category',
        'sequence',
        'status',
        'due_date',
        'completed_at',
        'assigned_to',
        'completed_by',
        'notes',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sequence' => 0,
        'status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<CandidateApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(CandidateApplication::class, 'application_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'completed_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null && $this->due_date->isPast() && ! $this->isCompleted();
    }

    public function start(): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        $this->status = 'in_progress';

        return $this->save();
    }

    public function complete(int $completedById): bool
    {
        $this->status = 'completed';
        $this->completed_by = $completedById;
        $this->completed_at = \Illuminate\Support\Carbon::now();

        return $this->save();
    }

    public function skip(): bool
    {
        $this->status = 'skipped';

        return $this->save();
    }

    /**
     * @return array<string, string>
     */
    public static function categories(): array
    {
        return [
            self::CATEGORY_DOCUMENTATION => 'Documentation',
            self::CATEGORY_TRAINING => 'Training',
            self::CATEGORY_EQUIPMENT => 'Equipment',
            self::CATEGORY_ACCESS => 'Access',
            self::CATEGORY_ORIENTATION => 'Orientation',
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
            ->whereNotIn('status', ['completed', 'skipped']);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeOfCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sequence');
    }
}
