<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Leave;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LeaveBalance model - Track employee leave balances by category and year.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $employee_id
 * @property int $leave_category_id
 * @property int $year
 * @property float $entitled_days
 * @property float $carried_forward_days
 * @property float $used_days
 * @property float $pending_days
 * @property float $remaining_days
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class LeaveBalance extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;

    protected $table = 'hrms_leave_balances';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'leave_category_id',
        'year',
        'entitled_days',
        'carried_forward_days',
        'used_days',
        'pending_days',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'entitled_days' => 0.0,
        'carried_forward_days' => 0.0,
        'used_days' => 0.0,
        'pending_days' => 0.0,
    ];

    /**
     * Get or create a balance for an employee, category, and year with mid-year joiner proration.
     */
    public static function getOrCreateForEmployee(
        int $employeeId,
        int $leaveCategoryId,
        ?int $year = null
    ): self {
        $year ??= (int) date('Y');

        $existing = self::where([
            'employee_id' => $employeeId,
            'leave_category_id' => $leaveCategoryId,
            'year' => $year,
        ])->first();

        if ($existing !== null) {
            return $existing;
        }

        $employee = Employee::with('currentJob')->find($employeeId);
        $category = LeaveCategory::find($leaveCategoryId);
        $defaultDays = $category !== null ? (float) $category->default_days : 0.0;

        $startDate = $employee?->currentJob?->start_date;
        if ($startDate && (int) $startDate->year === $year) {
            $service = app(\App\Modules\HrmsCore\Services\LeaveAccrualService::class);
            $defaultDays = $service->calculateProratedEntitlement($defaultDays, $startDate, $year);
        }

        return self::create([
            'tenant_id' => $employee?->tenant_id,
            'employee_id' => $employeeId,
            'leave_category_id' => $leaveCategoryId,
            'year' => $year,
            'entitled_days' => $defaultDays,
            'carried_forward_days' => 0.0,
            'used_days' => 0.0,
            'pending_days' => 0.0,
        ]);
    }

    /**
     * Get the employee this balance belongs to.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the leave category.
     *
     * @return BelongsTo<LeaveCategory, $this>
     */
    public function leaveCategory(): BelongsTo
    {
        return $this->belongsTo(LeaveCategory::class);
    }

    public function getEntitledDaysAttribute(mixed $value): int|float
    {
        $val = (float) $value;

        return fmod($val, 1.0) === 0.0 ? (int) $val : $val;
    }

    public function getCarriedForwardDaysAttribute(mixed $value): int|float
    {
        $val = (float) $value;

        return fmod($val, 1.0) === 0.0 ? (int) $val : $val;
    }

    public function getUsedDaysAttribute(mixed $value): int|float
    {
        $val = (float) $value;

        return fmod($val, 1.0) === 0.0 ? (int) $val : $val;
    }

    public function getPendingDaysAttribute(mixed $value): int|float
    {
        $val = (float) $value;

        return fmod($val, 1.0) === 0.0 ? (int) $val : $val;
    }

    /**
     * Get total available days (entitled + carry forward).
     */
    public function getTotalAvailable(): int|float
    {
        $val = (float) ($this->entitled_days + $this->carried_forward_days);

        return fmod($val, 1.0) === 0.0 ? (int) $val : $val;
    }

    /**
     * Get total consumed days (used + pending).
     */
    public function getTotalConsumed(): int|float
    {
        $val = (float) ($this->used_days + $this->pending_days);

        return fmod($val, 1.0) === 0.0 ? (int) $val : $val;
    }

    /**
     * Get remaining days attribute with in-memory fallback for un-refreshed instances.
     */
    public function getRemainingDaysAttribute(mixed $value): int|float
    {
        $val = $value !== null && is_numeric($value)
            ? (float) $value
            : max(0.0, (float) ($this->entitled_days + $this->carried_forward_days - $this->used_days - $this->pending_days));

        return fmod($val, 1.0) === 0.0 ? (int) $val : $val;
    }

    /**
     * Check if the balance has enough days for a request.
     */
    public function hasEnoughDays(float $requestedDays): bool
    {
        return $this->remaining_days >= $requestedDays;
    }

    /**
     * Add pending days for a new leave request.
     */
    public function addPendingDays(float $days): void
    {
        $this->pending_days += $days;
        $this->save();
    }

    /**
     * Remove pending days when request is rejected or cancelled.
     */
    public function removePendingDays(float $days): void
    {
        $this->pending_days = max(0.0, (float) ($this->pending_days - $days));
        $this->save();
    }

    /**
     * Convert pending days to used days when request is approved.
     */
    public function convertPendingToUsed(float $days): void
    {
        $this->pending_days = max(0.0, (float) ($this->pending_days - $days));
        $this->used_days += $days;
        $this->save();
    }

    /**
     * Restore days when a leave is recalled.
     */
    public function restoreRecalledDays(float $days): void
    {
        $this->used_days = max(0.0, (float) ($this->used_days - $days));
        $this->save();
    }

    /**
     * Scope to a specific year.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    /**
     * Scope to current year.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeCurrentYear($query)
    {
        return $query->where('year', (int) date('Y'));
    }

    /**
     * Scope to a specific employee.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope to a specific leave category.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForCategory($query, int $categoryId)
    {
        return $query->where('leave_category_id', $categoryId);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'entitled_days' => 'float',
            'carried_forward_days' => 'float',
            'used_days' => 'float',
            'pending_days' => 'float',
            'remaining_days' => 'float',
        ];
    }
}
