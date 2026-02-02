<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Salary;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * EmployeeAllowance model - Allowances assigned to employees.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $employee_id
 * @property int $allowance_id
 * @property string|null $amount
 * @property \Illuminate\Support\Carbon $effective_from
 * @property \Illuminate\Support\Carbon|null $effective_to
 * @property bool $is_active
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class EmployeeAllowance extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;

    protected $table = 'hrms_employee_allowances';

    protected $connection = 'landlord';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'allowance_id',
        'amount',
        'effective_from',
        'effective_to',
        'is_active',
        'notes',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the employee.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the allowance.
     *
     * @return BelongsTo<Allowance, $this>
     */
    public function allowance(): BelongsTo
    {
        return $this->belongsTo(Allowance::class);
    }

    /**
     * Get the effective amount (override or default).
     */
    public function getEffectiveAmount(): float
    {
        if ($this->amount !== null) {
            return (float) $this->amount;
        }

        if ($this->allowance === null) {
            return 0.0;
        }

        return (float) $this->allowance->amount;
    }

    /**
     * Check if the allowance is currently effective.
     */
    public function isCurrentlyEffective(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $today = now()->startOfDay();

        if ($this->effective_from->gt($today)) {
            return false;
        }

        if ($this->effective_to !== null && $this->effective_to->lt($today)) {
            return false;
        }

        return true;
    }

    /**
     * Check if this is a taxable allowance.
     */
    public function isTaxable(): bool
    {
        return $this->allowance?->isTaxable() ?? true;
    }

    /**
     * Scope to only active employee allowances.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to currently effective allowances.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeCurrentlyEffective($query)
    {
        $today = now()->startOfDay();

        return $query->where('is_active', true)
            ->where('effective_from', '<=', $today)
            ->where(function ($q) use ($today): void {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $today);
            });
    }

    /**
     * Scope to filter by employee.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope to filter by allowance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForAllowance($query, int $allowanceId)
    {
        return $query->where('allowance_id', $allowanceId);
    }
}
