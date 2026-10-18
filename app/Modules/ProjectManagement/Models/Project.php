<?php

declare(strict_types=1);

namespace App\Modules\ProjectManagement\Models;

use App\Models\User;
use App\Modules\ProjectManagement\Models\Concerns\HasProjectUuid;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $status
 * @property string $priority
 * @property \Illuminate\Support\Carbon|null $start_date
 * @property \Illuminate\Support\Carbon|null $end_date
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property numeric-string|null $budget_amount
 * @property numeric-string $actual_cost_amount
 * @property string|null $currency
 * @property string|null $owner_id
 * @property array<string, mixed>|null $metadata
 */
final class Project extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasProjectUuid;
    use SoftDeletes;

    public const STATUSES = ['planned', 'in-progress', 'on-hold', 'completed', 'archived'];

    public const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    protected $fillable = [
        'uuid',
        'tenant_id',
        'name',
        'slug',
        'description',
        'status',
        'priority',
        'start_date',
        'end_date',
        'completed_at',
        'budget_amount',
        'actual_cost_amount',
        'currency',
        'owner_id',
        'metadata',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id', 'uuid');
    }

    /**
     * @return HasMany<ProjectMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    /**
     * @return HasMany<Milestone, $this>
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class);
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * @return HasMany<ResourceAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(ResourceAllocation::class);
    }

    /**
     * @return HasMany<ProjectRisk, $this>
     */
    public function risks(): HasMany
    {
        return $this->hasMany(ProjectRisk::class);
    }

    public function budgetVariance(): float
    {
        $budget = (float) ($this->budget_amount ?? 0);
        $actual = (float) ($this->actual_cost_amount ?? 0);

        return round($budget - $actual, 2);
    }

    public function budgetExecutionPercent(): float
    {
        $budget = (float) ($this->budget_amount ?? 0);
        $actual = (float) ($this->actual_cost_amount ?? 0);

        if ($budget <= 0) {
            return 0.0;
        }

        return round(($actual / $budget) * 100, 1);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'completed_at' => 'datetime',
            'budget_amount' => 'decimal:2',
            'actual_cost_amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }
}
