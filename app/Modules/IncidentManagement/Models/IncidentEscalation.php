<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $incident_id
 * @property int|null $from_priority_id
 * @property int|null $to_priority_id
 * @property string|null $escalated_by_id
 * @property string|null $reason
 * @property \Illuminate\Support\Carbon|null $escalated_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class IncidentEscalation extends Model
{
    use BelongsToTenant;

    protected $table = 'incident_escalations';

    protected $fillable = [
        'tenant_id',
        'incident_id',
        'from_priority_id',
        'to_priority_id',
        'escalated_by_id',
        'reason',
        'escalated_at',
    ];

    /**
     * @return BelongsTo<Incident, $this>
     */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class, 'incident_id');
    }

    /**
     * @return BelongsTo<IncidentPriority, $this>
     */
    public function fromPriority(): BelongsTo
    {
        return $this->belongsTo(IncidentPriority::class, 'from_priority_id');
    }

    /**
     * @return BelongsTo<IncidentPriority, $this>
     */
    public function toPriority(): BelongsTo
    {
        return $this->belongsTo(IncidentPriority::class, 'to_priority_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function escalatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_by_id', 'uuid');
    }

    protected function casts(): array
    {
        return [
            'escalated_at' => 'datetime',
        ];
    }
}
