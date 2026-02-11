<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Alert extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'qm_alerts';

    protected $fillable = [
        'tenant_id',
        'workplan_id',
        'kpi_id',
        'type',
        'status',
        'metadata',
        'sent_at',
        'escalation_level',
        'escalated_at',
    ];

    /**
     * @return BelongsTo<Workplan, $this>
     */
    public function workplan(): BelongsTo
    {
        return $this->belongsTo(Workplan::class);
    }

    /**
     * @return BelongsTo<Kpi, $this>
     */
    public function kpi(): BelongsTo
    {
        return $this->belongsTo(Kpi::class);
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'sent_at' => 'datetime',
            'escalated_at' => 'datetime',
            'escalation_level' => 'integer',
        ];
    }
}
