<?php

declare(strict_types=1);

namespace App\Modules\Intranet\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $intranet_event_id
 * @property string $user_id
 * @property string $status
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read IntranetEvent $event
 * @property-read User|null $user
 */
final class IntranetEventRsvp extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const string STATUS_ATTENDING = 'attending';

    public const string STATUS_DECLINED = 'declined';

    public const string STATUS_TENTATIVE = 'tentative';

    public const array STATUSES = [
        self::STATUS_ATTENDING,
        self::STATUS_DECLINED,
        self::STATUS_TENTATIVE,
    ];

    protected $table = 'intranet_event_rsvps';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'intranet_event_id',
        'user_id',
        'status',
        'notes',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<IntranetEvent, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(IntranetEvent::class, 'intranet_event_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
    }
}
