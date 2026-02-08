<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class KpiUpdate extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'qm_kpi_updates';

    protected $fillable = [
        'tenant_id',
        'kpi_id',
        'value',
        'captured_at',
        'note',
        'captured_by_id',
        'evidence_path',
        'evidence_mime',
        'evidence_size',
    ];

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
            'captured_at' => 'datetime',
            'value' => 'decimal:2',
        ];
    }
}
