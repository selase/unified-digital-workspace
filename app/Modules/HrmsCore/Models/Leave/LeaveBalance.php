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
 * @property int $entitled_days
 * @property int $carried_forward_days
 * @property int $used_days
 * @property int $pending_days
 * @property int $remaining_days
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
        'entitled_days' => 0,
        'carried_forward_days' => 0,
        'used_days' => 0,
        'pending_days' => 0,
    ];

    /**
     * Get or create a balance for an employee, category, and year.
     */
    public static function getOrCreateForEmployee(
        int $employeeId,
        int $leaveCategoryId,
        ?int $year = null
    ): self {
        $year ??= (int) date('Y');
        $category = LeaveCategory::find($leaveCategoryId);
        $defaultDays = $category !== null ? $category->default_days : 0;

        return self::firstOrCreate(
            [
                'employee_id' => $employeeId,
                'leave_category_id' => $leaveCategoryId,
                'year' => $year,
            ],
            [
                'entitled_days' => $defaultDays,
                'carried_forward_days' => 0,
                'used_days' => 0,
                'pending_days' => 0,
            ]
        );
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

    /**
     * Get total available days (entitled + carry forward).
     */
    public function getTotalAvailable(): int
    {
        return $this->entitled_days + $this->carried_forward_days;
    }

    /**
     * Get total consumed days (used + pending).
     */
    public function getTotalConsumed(): int
    {
        return $this->used_days + $this->pending_days;
    }

    /**
     * Check if the balance has enough days for a request.
     */
    public function hasEnoughDays(int $requestedDays): bool
    {
        return $this->remaining_days >= $requestedDays;
    }

    /**
     * Add pending days for a new leave request.
     */
    public function addPendingDays(int $days): void
    {
        $this->pending_days += $days;
        $this->save();
    }

    /**
     * Remove pending days when request is rejected or cancelled.
     */
    public function removePendingDays(int $days): void
    {
        $this->pending_days = max(0, $this->pending_days - $days);
        $this->save();
    }

    /**
     * Convert pending days to used days when request is approved.
     */
    public function convertPendingToUsed(int $days): void
    {
        $this->pending_days = max(0, $this->pending_days - $days);
        $this->used_days += $days;
        $this->save();
    }

    /**
     * Restore days when a leave is recalled.
     */
    public function restoreRecalledDays(int $days): void
    {
        $this->used_days = max(0, $this->used_days - $days);
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
            'entitled_days' => 'integer',
            'carried_forward_days' => 'integer',
            'used_days' => 'integer',
            'pending_days' => 'integer',
            'remaining_days' => 'integer',
        ];
    }
}
