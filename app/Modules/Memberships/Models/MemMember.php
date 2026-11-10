<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Models;

use App\Modules\Finance\Models\FinContact;
use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class MemMember extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    public const STATUSES = ['pending' => 'Awaiting first payment', 'active' => 'Active', 'expired' => 'Expired', 'cancelled' => 'Cancelled'];

    protected $table = 'mem_members';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @return BelongsTo<MemType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(MemType::class, 'type_id');
    }

    /** @return BelongsTo<FinContact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(FinContact::class, 'contact_id');
    }

    /** @return HasMany<MemRenewal, $this> */
    public function renewals(): HasMany
    {
        return $this->hasMany(MemRenewal::class, 'member_id');
    }

    protected function casts(): array
    {
        return ['joined_on' => 'date', 'expires_on' => 'date'];
    }
}
