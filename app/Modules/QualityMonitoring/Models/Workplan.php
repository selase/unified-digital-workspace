<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Workplan extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuid;

    protected $table = 'qm_workplans';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'title',
        'period_start',
        'period_end',
        'status',
        'owner_id',
        'org_scope',
        'metadata',
    ];

    /**
     * @return HasMany<WorkplanVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(WorkplanVersion::class);
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'org_scope' => 'array',
            'metadata' => 'array',
        ];
    }
}
