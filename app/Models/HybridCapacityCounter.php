<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $meter
 * @property string $period
 * @property int $bytes
 * @property \Carbon\CarbonImmutable|null $audited_at
 */
final class HybridCapacityCounter extends Model
{
    use HasUuids;

    protected $connection = 'landlord';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['bytes' => 'integer', 'audited_at' => 'immutable_datetime'];
    }
}
