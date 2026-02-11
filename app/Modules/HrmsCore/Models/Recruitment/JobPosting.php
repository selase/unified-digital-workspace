<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Recruitment;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * JobPosting model - Published job listings.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $requisition_id
 * @property string $title
 * @property string|null $description
 * @property string|null $requirements
 * @property string|null $benefits
 * @property string|null $slug
 * @property bool $is_internal
 * @property bool $is_external
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $posted_date
 * @property \Illuminate\Support\Carbon|null $closing_date
 * @property int $views_count
 * @property int $applications_count
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
final class JobPosting extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;
    use SoftDeletes;

    protected $table = 'hrms_job_postings';

    protected $fillable = [
        'tenant_id',
        'requisition_id',
        'title',
        'description',
        'requirements',
        'benefits',
        'slug',
        'is_internal',
        'is_external',
        'is_active',
        'posted_date',
        'closing_date',
        'views_count',
        'applications_count',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_internal' => false,
        'is_external' => true,
        'is_active' => true,
        'views_count' => 0,
        'applications_count' => 0,
    ];

    /**
     * @return BelongsTo<JobRequisition, $this>
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(JobRequisition::class, 'requisition_id');
    }

    /**
     * @return HasMany<CandidateApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(CandidateApplication::class, 'posting_id');
    }

    public function isExpired(): bool
    {
        return $this->closing_date !== null && $this->closing_date->isPast();
    }

    public function isAcceptingApplications(): bool
    {
        return $this->is_active && ! $this->isExpired();
    }

    public function incrementViews(): void
    {
        $this->increment('views_count');
    }

    public function incrementApplications(): void
    {
        $this->increment('applications_count');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q): void {
                $q->whereNull('closing_date')
                    ->orWhere('closing_date', '>=', now());
            });
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeInternal($query)
    {
        return $query->where('is_internal', true);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeExternal($query)
    {
        return $query->where('is_external', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'is_external' => 'boolean',
            'is_active' => 'boolean',
            'posted_date' => 'date',
            'closing_date' => 'date',
            'views_count' => 'integer',
            'applications_count' => 'integer',
        ];
    }
}
