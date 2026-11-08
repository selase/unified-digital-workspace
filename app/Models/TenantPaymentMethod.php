<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A card a customer agreed to have charged for future invoices. The Paystack
 * authorization code is stored encrypted and is never shown.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $authorization_code
 * @property string $signature
 * @property string $email
 * @property string|null $brand
 * @property string|null $last4
 * @property Carbon $consented_at
 * @property Carbon|null $removed_at
 */
final class TenantPaymentMethod extends Model
{
    use HasUuids;

    protected $connection = 'landlord';

    protected $guarded = [];

    protected $hidden = ['authorization_code'];

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function description(): string
    {
        return mb_trim(($this->brand ? ucfirst($this->brand).' ' : '').'card ending '.$this->last4);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['authorization_code' => 'encrypted', 'consented_at' => 'datetime', 'removed_at' => 'datetime'];
    }
}
