<?php

declare(strict_types=1);

namespace App\Modules\ProjectManagement\Models;

use App\Models\User;
use App\Modules\ProjectManagement\Models\Concerns\HasProjectUuid;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ProjectRisk model - 5x5 Likelihood x Impact Risk Governance.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $project_id
 * @property string $title
 * @property string|null $description
 * @property string $category
 * @property int $likelihood
 * @property int $impact
 * @property int $risk_score
 * @property string $status
 * @property string|null $mitigation_strategy
 * @property string|null $contingency_plan
 * @property string|null $owner_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class ProjectRisk extends Model
{
    use BelongsToTenant;
    use HasProjectUuid;

    public const CATEGORIES = [
        'technical' => 'Technical & Architecture',
        'schedule' => 'Schedule & Timeline',
        'financial' => 'Financial & Budgetary',
        'operational' => 'Operational & Clinical',
        'governance' => 'Governance & Compliance',
        'external' => 'External & Vendor',
    ];

    public const STATUSES = [
        'identified' => 'Identified',
        'analyzed' => 'Analyzed',
        'mitigated' => 'Mitigated',
        'closed' => 'Closed',
    ];

    protected $table = 'project_risks';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'project_id',
        'title',
        'description',
        'category',
        'likelihood',
        'impact',
        'status',
        'mitigation_strategy',
        'contingency_plan',
        'owner_id',
    ];

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id', 'uuid');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst($this->category);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function severityLevel(): string
    {
        $score = $this->risk_score ?: ($this->likelihood * $this->impact);

        return match (true) {
            $score >= 15 => 'critical',
            $score >= 10 => 'high',
            $score >= 5 => 'medium',
            default => 'low',
        };
    }

    public function severityVariant(): string
    {
        return match ($this->severityLevel()) {
            'critical' => 'destructive',
            'high' => 'warning',
            'medium' => 'primary',
            default => 'success',
        };
    }

    public function statusVariant(): string
    {
        return match ($this->status) {
            'closed' => 'muted',
            'mitigated' => 'success',
            'analyzed' => 'primary',
            default => 'warning',
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'likelihood' => 'integer',
            'impact' => 'integer',
            'risk_score' => 'integer',
        ];
    }
}
