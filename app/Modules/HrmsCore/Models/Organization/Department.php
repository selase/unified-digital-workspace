<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Organization;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Department model - Top-level organizational units.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class Department extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;

    protected $table = 'hrms_departments';

    protected $connection = 'landlord';

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
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
     * Get the department types for this department.
     *
     * @return HasMany<DepartmentType, $this>
     */
    public function departmentTypes(): HasMany
    {
        return $this->hasMany(DepartmentType::class);
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

    // Note: Relationship to Employee (belongsToMany via hrms_department_employee)
    // will be added in Phase 2 when the Employee model is created.
}
