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

/**
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property string $job_number
 * @property int|null $vendor_profile_id
 * @property string $requester_id
 * @property string $source_module
 * @property string $category
 * @property string $title
 * @property string $description
 * @property string $location
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
final class ServiceJob extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    public const STATUS_OPEN = 'open';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_QUOTED = 'quoted';

    public const STATUS_IN_ESCROW = 'in_escrow';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_DISPUTED = 'disputed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'vendor_service_jobs';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'job_number',
        'vendor_profile_id',
        'requester_id',
        'source_module',
        'source_reference_id',
        'category',
        'title',
        'description',
        'location',
        'status',
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
     * @return BelongsTo<VendorProfile, $this>
     */
    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_profile_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id', 'uuid');
    }

    /**
     * @return HasMany<ProformaInvoice, $this>
     */
    public function proformaInvoices(): HasMany
    {
        return $this->hasMany(ProformaInvoice::class, 'service_job_id');
    }
}
