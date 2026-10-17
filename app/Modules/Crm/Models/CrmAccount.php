<?php

declare(strict_types=1);

namespace App\Modules\Crm\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class CrmAccount extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'crm_accounts';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'name',
        'code',
        'account_type',
        'industry',
        'email',
        'phone',
        'address',
        'owner_id',
        'portal_user_id',
        'status',
        'metadata',
    ];

    public function getConnectionName(): string
    {
        return config('database.default_tenant_connection', 'tenant');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id', 'uuid');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function portalUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'portal_user_id', 'uuid');
    }

    /**
     * @return HasMany<CrmContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(CrmContact::class, 'account_id');
    }

    /**
     * @return HasMany<CrmDeal, $this>
     */
    public function deals(): HasMany
    {
        return $this->hasMany(CrmDeal::class, 'account_id');
    }

    /**
     * @return MorphMany<CrmActivity, $this>
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(CrmActivity::class, 'subject');
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}
