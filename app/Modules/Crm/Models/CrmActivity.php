<?php

declare(strict_types=1);

namespace App\Modules\Crm\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property string $subject_type
 * @property int $subject_id
 * @property string $activity_type
 * @property string $summary
 * @property string|null $details
 * @property string|null $logged_by_id
 * @property \Illuminate\Support\Carbon $occurred_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User|null $loggedBy
 * @property-read Model|null $subject
 */
final class CrmActivity extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'crm_activities';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'subject_type',
        'subject_id',
        'activity_type',
        'summary',
        'details',
        'logged_by_id',
        'occurred_at',
    ];

    public function getConnectionName(): string
    {
        return config('database.default_tenant_connection', 'tenant');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function loggedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by_id', 'uuid');
    }

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }
}
