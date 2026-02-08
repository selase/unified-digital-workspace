<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Variance extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'qm_variances';

    protected $fillable = [
        'tenant_id',
        'workplan_id',
        'activity_id',
        'kpi_id',
        'category',
        'impact_level',
        'narrative',
        'corrective_action',
        'revised_date',
        'evidence_path',
        'evidence_mime',
        'evidence_size',
        'status',
        'reviewed_by_id',
        'reviewed_at',
    ];

    /**
     * @return BelongsTo<Workplan, $this>
     */
    public function workplan(): BelongsTo
    {
        return $this->belongsTo(Workplan::class);
    }

    protected function casts(): array
    {
        return [
            'revised_date' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }
}
