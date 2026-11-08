<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A recurring discount applied to a tenant's generated invoices while it is active
 * and its date range overlaps the billed period.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string $reason
 * @property string $type
 * @property string $value
 * @property string|null $currency
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property bool $is_active
 * @property Tenant $tenant
 */
final class TenantDiscount extends Model
{
    use HasUuids;

    public const TYPE_PERCENT = 'percent';

    public const TYPE_FIXED = 'fixed';

    protected $connection = 'landlord';

    protected $guarded = [];

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @param  Builder<TenantDiscount>  $query
     * @return Builder<TenantDiscount>
     */
    public function scopeOverlapping(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->where('is_active', true)
            ->whereDate('starts_on', '<=', $end)
            ->where(fn (Builder $q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $start));
    }

    public function label(): string
    {
        return $this->type === self::TYPE_PERCENT
            ? mb_rtrim(mb_rtrim((string) $this->value, '0'), '.').'%'
            : ($this->currency ?? '').' '.number_format((float) $this->value, 2);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'is_active' => 'boolean', 'value' => 'decimal:4'];
    }
}
