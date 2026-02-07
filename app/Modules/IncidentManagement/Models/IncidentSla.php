<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $incident_id
 * @property \Illuminate\Support\Carbon|null $response_due_at
 * @property \Illuminate\Support\Carbon|null $resolution_due_at
 * @property \Illuminate\Support\Carbon|null $first_response_at
 * @property \Illuminate\Support\Carbon|null $resolution_at
 * @property bool $is_breached
 * @property \Illuminate\Support\Carbon|null $breached_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class IncidentSla extends Model
{
    protected $table = 'incident_slas';

    protected $connection = 'landlord';

    protected $fillable = [
        'incident_id',
        'response_due_at',
        'resolution_due_at',
        'first_response_at',
        'resolution_at',
        'is_breached',
        'breached_at',
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
            'response_due_at' => 'datetime',
            'resolution_due_at' => 'datetime',
            'first_response_at' => 'datetime',
            'resolution_at' => 'datetime',
            'breached_at' => 'datetime',
            'is_breached' => 'boolean',
        ];
    }
}
