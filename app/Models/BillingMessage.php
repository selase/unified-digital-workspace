<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string|null $invoice_id
 * @property string $recipient
 * @property string $kind
 * @property string $status
 * @property array<string, mixed> $payload
 * @property int $attempts
 * @property string|null $last_error
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $lease_until
 * @property Tenant $tenant
 * @property Invoice|null $invoice
 */
final class BillingMessage extends Model
{
    use HasUuids;

    protected $connection = 'landlord';

    protected $guarded = [];

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['payload' => 'array', 'attempts' => 'integer', 'sent_at' => 'datetime', 'lease_until' => 'datetime'];
    }
}
