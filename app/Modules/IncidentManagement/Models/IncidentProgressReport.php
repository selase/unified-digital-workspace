<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $incident_id
 * @property string $user_id
 * @property string $body
 * @property bool $is_internal
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class IncidentProgressReport extends Model
{
    use BelongsToTenant;

    protected $table = 'incident_progress_reports';

    protected $fillable = [
        'tenant_id',
        'incident_id',
        'user_id',
        'body',
        'is_internal',
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<IncidentProgressComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(IncidentProgressComment::class, 'progress_report_id');
    }

    /**
     * @return HasMany<IncidentAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(IncidentAttachment::class, 'progress_report_id');
    }

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
        ];
    }
}
