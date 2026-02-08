<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WorkplanVersion extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'qm_workplan_versions';

    protected $fillable = [
        'tenant_id',
        'workplan_id',
        'version_no',
        'status',
        'payload',
        'submitted_at',
        'approved_at',
        'created_by',
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
            'payload' => 'array',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }
}
