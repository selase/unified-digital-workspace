<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Organization;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Unit model - Smallest organizational units.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int|null $department_id
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
final class Unit extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;

    protected $table = 'hrms_units';

    protected $fillable = [
        'tenant_id',
        'department_id',
        'directorate_id',
        'name',
        'slug',
        'description',
        'head_employee_id',
        'deputy_employee_id',
        'is_active',
    ];

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * @return BelongsTo<Directorate, $this>
     */
    public function directorate(): BelongsTo
    {
        return $this->belongsTo(Directorate::class, 'directorate_id');
    }

    /**
     * @return BelongsToMany<Employee, $this>
     */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'hrms_employee_unit');
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
     * Scope to only active units.
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

        self::creating(function (Unit $unit): void {
            if (empty($unit->slug)) {
                $unit->slug = Str::slug($unit->name);
            }
        });

        self::saving(function (Unit $unit): void {
            if ($unit->head_employee_id !== null && $unit->head_employee_id === $unit->deputy_employee_id) {
                throw new InvalidArgumentException('An employee cannot be both the Head and Deputy of the same unit.');
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
            'department_id' => 'integer',
            'directorate_id' => 'integer',
            'head_employee_id' => 'integer',
            'deputy_employee_id' => 'integer',
        ];
    }
}
