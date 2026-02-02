<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Recruitment;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CandidateDocument model - Resumes, cover letters, certificates.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $candidate_id
 * @property int|null $application_id
 * @property string $type
 * @property string $name
 * @property string $file_path
 * @property string|null $mime_type
 * @property int|null $file_size
 * @property bool $is_primary
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class CandidateDocument extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;

    protected $table = 'hrms_candidate_documents';

    protected $connection = 'landlord';

    public const TYPE_RESUME = 'resume';

    public const TYPE_COVER_LETTER = 'cover_letter';

    public const TYPE_CERTIFICATE = 'certificate';

    public const TYPE_ID_CARD = 'id_card';

    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'tenant_id',
        'candidate_id',
        'application_id',
        'type',
        'name',
        'file_path',
        'mime_type',
        'file_size',
        'is_primary',
        'notes',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_primary' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Candidate, $this>
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /**
     * @return BelongsTo<CandidateApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(CandidateApplication::class, 'application_id');
    }

    public function isResume(): bool
    {
        return $this->type === self::TYPE_RESUME;
    }

    public function isCoverLetter(): bool
    {
        return $this->type === self::TYPE_COVER_LETTER;
    }

    /**
     * @return array<string, string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_RESUME => 'Resume',
            self::TYPE_COVER_LETTER => 'Cover Letter',
            self::TYPE_CERTIFICATE => 'Certificate',
            self::TYPE_ID_CARD => 'ID Card',
            self::TYPE_OTHER => 'Other',
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }
}
