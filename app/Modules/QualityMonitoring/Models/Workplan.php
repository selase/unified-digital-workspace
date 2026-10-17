<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property string $title
 * @property \Illuminate\Support\Carbon|null $period_start
 * @property \Illuminate\Support\Carbon|null $period_end
 * @property string $status
 * @property string|null $owner_id
 * @property array<string, mixed>|null $org_scope
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
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

    /**
     * @return HasMany<Objective, $this>
     */
    public function objectives(): HasMany
    {
        return $this->hasMany(Objective::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, $this>
     */
    public function owner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'owner_id', 'uuid');
    }

    /**
     * @return HasMany<Variance, $this>
     */
    public function variances(): HasMany
    {
        return $this->hasMany(Variance::class);
    }

    /**
     * @return HasMany<Alert, $this>
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
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
