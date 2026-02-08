<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Review extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'qm_reviews';

    protected $fillable = [
        'tenant_id',
        'workplan_id',
        'reviewer_id',
        'status',
        'comments',
        'scores',
        'submitted_at',
        'approved_at',
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
            'scores' => 'array',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }
}
