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
 * @property int $progress_report_id
 * @property string $user_id
 * @property string $body
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class IncidentProgressComment extends Model
{
    use BelongsToTenant;

    protected $table = 'incident_progress_comments';

    protected $fillable = [
        'tenant_id',
        'progress_report_id',
        'user_id',
        'body',
    ];

    /**
     * @return BelongsTo<IncidentProgressReport, $this>
     */
    public function progressReport(): BelongsTo
    {
        return $this->belongsTo(IncidentProgressReport::class, 'progress_report_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
