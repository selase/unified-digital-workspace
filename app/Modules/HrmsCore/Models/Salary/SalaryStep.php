<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Salary;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Models\Organization\Grade;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * SalaryStep model - Step increments within salary levels.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property string $name
 * @property string $slug
 * @property int|null $grade_id
 * @property int|null $salary_level_id
 * @property string $step_increment
 * @property string|null $description
 * @property int $step_number
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class SalaryStep extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;

    protected $table = 'hrms_salary_steps';

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'grade_id',
        'salary_level_id',
        'step_increment',
        'description',
        'step_number',
        'is_active',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'step_increment' => 0,
        'step_number' => 1,
        'is_active' => true,
    ];

    /**
     * Get the grade this salary step belongs to.
     *
     * @return BelongsTo<Grade, $this>
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    /**
     * Get the salary level this step belongs to.
     *
     * @return BelongsTo<SalaryLevel, $this>
     */
    public function salaryLevel(): BelongsTo
    {
        return $this->belongsTo(SalaryLevel::class);
    }

    /**
     * Get the employees at this salary step.
     *
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * Calculate the total salary (base + step increment).
     */
    public function calculateTotalSalary(): float
    {
        $baseSalary = 0.0;

        if ($this->salaryLevel !== null) {
            $baseSalary = (float) $this->salaryLevel->base_salary;
        }

        return $baseSalary + (float) $this->step_increment;
    }

    /**
     * Scope to only active salary steps.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by step number.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('step_number');
    }

    /**
     * Scope to filter by grade.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForGrade($query, int $gradeId)
    {
        return $query->where('grade_id', $gradeId);
    }

    /**
     * Scope to filter by salary level.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForLevel($query, int $levelId)
    {
        return $query->where('salary_level_id', $levelId);
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        self::creating(function (SalaryStep $step): void {
            if (empty($step->slug)) {
                $step->slug = Str::slug($step->name);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'step_increment' => 'decimal:2',
            'step_number' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
