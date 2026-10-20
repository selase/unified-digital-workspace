<?php

declare(strict_types=1);

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $plan_slug
 * @property string $currency
 * @property int $editor_seats
 * @property bool $setup_required
 * @property array<string, mixed> $snapshot
 * @property \Illuminate\Support\Carbon $effective_from
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class TenantBillingAgreement extends Model
{
    /** @use HasFactory<\Database\Factories\TenantBillingAgreementFactory> */
    use HasFactory;

    use HasUuids;

    protected $connection = 'landlord';

    protected $fillable = ['tenant_id', 'effective_from', 'plan_slug', 'currency', 'editor_seats', 'setup_required', 'snapshot'];

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new DomainException('Billing agreements are immutable. Schedule a new agreement for a future billing month.');
        });
        self::deleting(function (): never {
            throw new DomainException('Billing agreements are retained for invoice history.');
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['effective_from' => 'date', 'editor_seats' => 'integer', 'setup_required' => 'boolean', 'snapshot' => 'array'];
    }
}
