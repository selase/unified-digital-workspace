<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $incident_id
 * @property string $reminder_type
 * @property \Illuminate\Support\Carbon $scheduled_for
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property string $channel
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class IncidentReminder extends Model
{
    use BelongsToTenant;

    protected $table = 'incident_reminders';

    protected $fillable = [
        'tenant_id',
        'incident_id',
        'reminder_type',
        'scheduled_for',
        'sent_at',
        'channel',
        'metadata',
    ];

    /**
     * @return BelongsTo<Incident, $this>
     */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class, 'incident_id');
    }

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
