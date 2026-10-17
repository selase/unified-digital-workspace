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
 * Directorate model - High-level organizational divisions.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int|null $head_employee_id
 * @property int|null $deputy_employee_id
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class Directorate extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;

    protected $table = 'hrms_directorates';

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'head_employee_id',
        'deputy_employee_id',
        'is_active',
    ];

    /**
     * @return HasMany<Department, $this>
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class, 'directorate_id');
    }

    /**
     * @return HasMany<Unit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class, 'directorate_id');
    }

    /**
     * @return BelongsToMany<Employee, $this>
     */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'hrms_directorate_employee');
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
     * Scope to only active directorates.
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

        self::creating(function (Directorate $directorate): void {
            if (empty($directorate->slug)) {
                $directorate->slug = Str::slug($directorate->name);
            }
        });

        self::saving(function (Directorate $directorate): void {
            if ($directorate->head_employee_id !== null && $directorate->head_employee_id === $directorate->deputy_employee_id) {
                throw new InvalidArgumentException('An employee cannot be both the Head and Deputy of the same directorate.');
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
            'head_employee_id' => 'integer',
            'deputy_employee_id' => 'integer',
        ];
    }
}
