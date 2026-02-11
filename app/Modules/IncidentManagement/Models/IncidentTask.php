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
 * @property string|null $assigned_to_id
 * @property string $title
 * @property string|null $description
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $due_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class IncidentTask extends Model
{
    use BelongsToTenant;

    protected $table = 'incident_tasks';

    protected $fillable = [
        'tenant_id',
        'incident_id',
        'assigned_to_id',
        'title',
        'description',
        'status',
        'due_at',
        'completed_at',
        'sort_order',
    ];

    /**
     * @return BelongsTo<Incident, $this>
     */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class, 'incident_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id', 'uuid');
    }

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }
}
