<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Organization;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Department model - Top-level or mid-tier organizational units.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int|null $directorate_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int|null $head_employee_id
 * @property int|null $deputy_employee_id
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class Department extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;

    protected $table = 'hrms_departments';

    protected $fillable = [
        'tenant_id',
        'directorate_id',
        'name',
        'slug',
        'description',
        'head_employee_id',
        'deputy_employee_id',
        'is_active',
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
     * Get the parent directorate for this department (if structured under a directorate).
     *
     * @return BelongsTo<Directorate, $this>
     */
    public function directorate(): BelongsTo
    {
        return $this->belongsTo(Directorate::class, 'directorate_id');
    }

    /**
     * Get the units under this department.
     *
     * @return HasMany<Unit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class, 'department_id');
    }

    /**
     * Get the department types for this department.
     *
     * @return HasMany<DepartmentType, $this>
     */
    public function departmentTypes(): HasMany
    {
        return $this->hasMany(DepartmentType::class);
    }

    /**
     * Get the employees for this department.
     *
     * @return BelongsToMany<Employee, $this>
     */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'hrms_department_employee');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function head(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'head_employee_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function deputy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'deputy_employee_id');
    }

    /**
     * @return MorphMany<OrgRoleAssignment, $this>
     */
    public function roleAssignments(): MorphMany
    {
        return $this->morphMany(OrgRoleAssignment::class, 'assignable');
    }

    /**
     * @return MorphMany<OrgPermissionAssignment, $this>
     */
    public function permissionAssignments(): MorphMany
    {
        return $this->morphMany(OrgPermissionAssignment::class, 'assignable');
    }

    /**
     * Scope to only active departments.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        self::creating(function (Department $department): void {
            if (empty($department->slug)) {
                $department->slug = Str::slug($department->name);
            }
        });

        self::saving(function (Department $department): void {
            if ($department->head_employee_id !== null && $department->head_employee_id === $department->deputy_employee_id) {
                throw new InvalidArgumentException('An employee cannot be both the Head and Deputy of the same department.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'directorate_id' => 'integer',
            'head_employee_id' => 'integer',
            'deputy_employee_id' => 'integer',
        ];
    }
}
