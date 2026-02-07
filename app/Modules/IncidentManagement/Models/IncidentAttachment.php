<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $incident_id
 * @property int|null $comment_id
 * @property int|null $progress_report_id
 * @property string $disk
 * @property string $path
 * @property string $filename
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $uploaded_by_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class IncidentAttachment extends Model
{
    protected $table = 'incident_attachments';

    protected $connection = 'landlord';

    protected $fillable = [
        'incident_id',
        'comment_id',
        'progress_report_id',
        'disk',
        'path',
        'filename',
        'mime_type',
        'size_bytes',
        'uploaded_by_id',
    ];

    /**
     * @return BelongsTo<Incident, $this>
     */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class, 'incident_id');
    }

    /**
     * @return BelongsTo<IncidentComment, $this>
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(IncidentComment::class, 'comment_id');
    }

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
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }
}
