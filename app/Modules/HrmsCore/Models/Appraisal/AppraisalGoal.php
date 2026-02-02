<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Appraisal;

use App\Modules\HrmsCore\Enums\GoalStatus;
use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AppraisalGoal model - Goals set for the appraisal period.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $appraisal_id
 * @property string $title
 * @property string|null $description
 * @property string|null $key_results
 * @property string|null $target
 * @property string|null $achievement
 * @property GoalStatus $status
 * @property string $weight
 * @property int|null $self_rating
 * @property int|null $supervisor_rating
 * @property string|null $employee_comments
 * @property string|null $supervisor_comments
 * @property \Illuminate\Support\Carbon|null $due_date
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class AppraisalGoal extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;

    protected $table = 'hrms_appraisal_goals';

    protected $connection = 'landlord';

    protected $fillable = [
        'tenant_id',
        'appraisal_id',
        'title',
        'description',
        'key_results',
        'target',
        'achievement',
        'status',
        'weight',
        'self_rating',
        'supervisor_rating',
        'employee_comments',
        'supervisor_comments',
        'due_date',
        'sort_order',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'not_started',
        'weight' => 0,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GoalStatus::class,
            'weight' => 'decimal:2',
            'self_rating' => 'integer',
            'supervisor_rating' => 'integer',
            'due_date' => 'date',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get the appraisal this goal belongs to.
     *
     * @return BelongsTo<Appraisal, $this>
     */
    public function appraisal(): BelongsTo
    {
        return $this->belongsTo(Appraisal::class);
    }

    /**
     * Check if the goal is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === GoalStatus::Completed;
    }

    /**
     * Check if the goal is in progress.
     */
    public function isInProgress(): bool
    {
        return $this->status === GoalStatus::InProgress;
    }

    /**
     * Check if the goal is overdue.
     */
    public function isOverdue(): bool
    {
        if ($this->due_date === null) {
            return false;
        }

        return ! $this->status->isFinal() && $this->due_date->lt(now()->startOfDay());
    }

    /**
     * Get the effective rating (supervisor or self).
     */
    public function getEffectiveRating(): ?int
    {
        return $this->supervisor_rating ?? $this->self_rating;
    }

    /**
     * Get the weighted score.
     */
    public function getWeightedScore(): float
    {
        $rating = $this->getEffectiveRating();

        if ($rating === null || (float) $this->weight === 0.0) {
            return 0.0;
        }

        return ($rating / 5) * (float) $this->weight;
    }

    /**
     * Scope to filter by appraisal.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForAppraisal($query, int $appraisalId)
    {
        return $query->where('appraisal_id', $appraisalId);
    }

    /**
     * Scope to filter by status.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeWithStatus($query, GoalStatus $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to only completed goals.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', GoalStatus::Completed);
    }

    /**
     * Scope to only overdue goals.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeOverdue($query)
    {
        return $query->whereNotIn('status', [
            GoalStatus::Completed,
            GoalStatus::Deferred,
            GoalStatus::Cancelled,
        ])->where('due_date', '<', now()->startOfDay());
    }

    /**
     * Scope to order by sort order.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
