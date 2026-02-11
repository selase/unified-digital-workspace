<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Organization;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * DepartmentType model - Sub-divisions within departments.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $department_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class DepartmentType extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;

    protected $table = 'hrms_department_types';

    protected $fillable = [
        'tenant_id',
        'department_id',
        'name',
        'slug',
        'description',
        'is_active',
    ];

    /**
     * Get the parent department.
     *
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Scope to only active department types.
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

        self::creating(function (DepartmentType $departmentType): void {
            if (empty($departmentType->slug)) {
                $departmentType->slug = Str::slug($departmentType->name);
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
        ];
    }

    // Note: Relationship to Employee (belongsToMany via hrms_department_type_employee)
    // will be added in Phase 2 when the Employee model is created.
}
