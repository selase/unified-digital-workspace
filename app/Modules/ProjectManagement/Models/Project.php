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
        'currency',
        'owner_id',
        'metadata',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
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

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'completed_at' => 'datetime',
            'budget_amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }
}
