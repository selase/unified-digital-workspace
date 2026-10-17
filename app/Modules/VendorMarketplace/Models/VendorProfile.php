<?php

declare(strict_types=1);

namespace App\Modules\VendorMarketplace\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class VendorProfile extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'vendor_profiles';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'user_id',
        'business_name',
        'code',
        'category',
        'license_number',
        'phone',
        'email',
        'status',
        'rating',
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
    }

    /**
     * @return HasMany<ServiceJob, $this>
     */
    public function jobs(): HasMany
    {
        return $this->hasMany(ServiceJob::class, 'vendor_profile_id');
    }

    /**
     * @return HasMany<ProformaInvoice, $this>
     */
    public function proformaInvoices(): HasMany
    {
        return $this->hasMany(ProformaInvoice::class, 'vendor_profile_id');
    }

    protected function casts(): array
    {
        return [
            'rating' => 'decimal:2',
        ];
    }
}
