<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class DataSource extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'qm_data_sources';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'method',
        'custodian',
        'quality_notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}
