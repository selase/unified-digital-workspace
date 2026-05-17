<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Tenancy\TenantContext;
use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aggregate-only properties exposed when this model is hydrated from
 * AggregateLlmUsage's GROUP BY query (see app/Console/Commands/AggregateLlmUsage.php).
 * They are not schema columns — listing them here lets static analysis verify
 * the aggregation pipeline without suppressing real undefined-property bugs.
 *
 * @property-read string|null $day                       CAST(created_at AS DATE)
 * @property-read int|null    $total_prompt_tokens      SUM(prompt_tokens)
 * @property-read int|null    $total_completion_tokens  SUM(completion_tokens)
 * @property-read int|null    $total_total_tokens       SUM(total_tokens)
 * @property-read float|null  $total_cost_usd           SUM(cost_usd)
 * @property-read int|null    $request_count            COUNT(*)
 */
final class LlmTokenUsage extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuid;
    use HasUuids;

    protected $connection = 'landlord';

    protected $guarded = [];

    protected $casts = [
        'context' => 'array',
        'cost_usd' => 'decimal:6',
        'total_tokens' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(TenantApiKey::class);
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        self::creating(function ($model) {
            if (! $model->tenant_id && $tenant = app(TenantContext::class)->getTenant()) {
                $model->tenant_id = $tenant->id;
            }
        });
    }
}
