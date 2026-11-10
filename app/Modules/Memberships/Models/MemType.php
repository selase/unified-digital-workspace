<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A kind of membership: its dues and how long one payment lasts. A fee of 0 is a free or honorary type. */
final class MemType extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'mem_types';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @var array<string, mixed> */
    protected $attributes = ['fee' => 0, 'period_months' => 12, 'is_public' => true, 'auto_invoice' => true, 'is_active' => true];

    /** @return HasMany<MemMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(MemMember::class, 'type_id');
    }

    protected function casts(): array
    {
        return ['is_public' => 'boolean', 'auto_invoice' => 'boolean', 'is_active' => 'boolean', 'fee' => 'integer', 'period_months' => 'integer'];
    }
}
