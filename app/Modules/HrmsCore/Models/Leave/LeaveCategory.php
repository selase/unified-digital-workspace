<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Leave;

use App\Modules\HrmsCore\Database\Factories\LeaveCategoryFactory;
use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * LeaveCategory model - Types of leave with default days.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property string $name
 * @property string $slug
 * @property int $default_days
 * @property string|null $description
 * @property bool $is_paid
 * @property bool $requires_documentation
 * @property bool $is_active
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @use HasFactory<LeaveCategoryFactory>
 */
final class LeaveCategory extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<LeaveCategoryFactory> */
    use HasFactory;

    use HasHrmsUuid;

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory<static>
     */
    protected static function newFactory(): \Illuminate\Database\Eloquent\Factories\Factory
    {
        return LeaveCategoryFactory::new();
    }

    protected $table = 'hrms_leave_categories';

    protected $connection = 'landlord';

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'default_days',
        'description',
        'is_paid',
        'requires_documentation',
        'is_active',
        'sort_order',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_paid' => true,
        'requires_documentation' => false,
        'is_active' => true,
        'sort_order' => 0,
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        self::creating(function (LeaveCategory $category): void {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_days' => 'integer',
            'is_paid' => 'boolean',
            'requires_documentation' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get the leave requests for this category.
     *
     * @return HasMany<LeaveRequest, $this>
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * Get the leave balances for this category.
     *
     * @return HasMany<LeaveBalance, $this>
     */
    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    /**
     * Scope to only active categories.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by sort order.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
