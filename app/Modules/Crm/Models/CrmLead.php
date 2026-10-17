<?php

declare(strict_types=1);

namespace App\Modules\Crm\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property string $reference_code
 * @property string $source
 * @property string|null $enquiry_type
 * @property string $full_name
 * @property string $email
 * @property string|null $phone
 * @property string|null $organisation
 * @property int|null $interested_listing_id
 * @property string|null $message
 * @property string $status
 * @property string|null $assigned_to_id
 * @property int|null $converted_account_id
 * @property int|null $converted_contact_id
 * @property \Illuminate\Support\Carbon|null $converted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
final class CrmLead extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    public const SOURCE_WEBSITE_ENQUIRY = 'website_enquiry';

    public const SOURCE_WALK_IN = 'walk_in';

    public const SOURCE_PROPERTY_LISTING = 'property_listing';

    public const SOURCE_REFERRAL = 'referral';

    public const SOURCE_DIRECT = 'direct';

    public const STATUS_NEW = 'new';

    public const STATUS_CONTACTED = 'contacted';

    public const STATUS_QUALIFIED = 'qualified';

    public const STATUS_CONVERTED = 'converted';

    public const STATUS_LOST = 'lost';

    protected $table = 'crm_leads';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'reference_code',
        'source',
        'enquiry_type',
        'full_name',
        'email',
        'phone',
        'organisation',
        'interested_listing_id',
        'message',
        'status',
        'assigned_to_id',
        'converted_account_id',
        'converted_contact_id',
        'converted_at',
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
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id', 'uuid');
    }

    /**
     * @return BelongsTo<CrmAccount, $this>
     */
    public function convertedAccount(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'converted_account_id');
    }

    /**
     * @return BelongsTo<CrmContact, $this>
     */
    public function convertedContact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'converted_contact_id');
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
            'converted_at' => 'datetime',
        ];
    }
}
