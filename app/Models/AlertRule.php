<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A tenant-defined rule: when `event` happens, notify the recipients on the chosen channels.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $event
 * @property string $name
 * @property bool $is_active
 * @property list<string> $channels
 * @property array{emails?: list<string>, phones?: list<string>}|null $recipients
 * @property array<string, mixed>|null $conditions
 */
final class AlertRule extends Model
{
    use HasUuids;

    protected $connection = 'landlord';

    protected $guarded = [];

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'channels' => 'array', 'recipients' => 'array', 'conditions' => 'array'];
    }
}
