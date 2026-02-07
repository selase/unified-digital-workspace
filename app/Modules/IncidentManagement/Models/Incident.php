<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Models;

use App\Models\User;
use App\Modules\IncidentManagement\Database\Factories\IncidentFactory;
use App\Modules\IncidentManagement\Models\Concerns\HasIncidentPrimaryUuid;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $title
 * @property string $description
 * @property int|null $category_id
 * @property int|null $priority_id
 * @property int|null $status_id
 * @property string|null $reported_by_id
 * @property int|null $reporter_id
 * @property string $reported_via
 * @property string|null $assigned_to_id
 * @property \Illuminate\Support\Carbon|null $due_at
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property string|null $source
 * @property string $reference_code
 * @property array<string, mixed>|null $metadata
 * @property string|null $impact
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @use HasFactory<IncidentFactory>
 */
final class Incident extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<IncidentFactory> */
    use HasFactory;

    use HasIncidentPrimaryUuid;
    use SoftDeletes;

    public $incrementing = false;

    protected $table = 'incidents';

    protected $connection = 'landlord';

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'title',
        'description',
        'category_id',
        'priority_id',
        'status_id',
        'reported_by_id',
        'reporter_id',
        'reported_via',
        'assigned_to_id',
        'due_at',
        'resolved_at',
        'closed_at',
        'source',
        'reference_code',
        'metadata',
        'impact',
    ];

    public function generateReferenceCode(): string
    {
        $year = now()->format('Y');
        $sequence = mb_str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);

        return 'INC'.$year.'-'.$sequence;
    }

    /**
     * @return BelongsTo<IncidentCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(IncidentCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<IncidentPriority, $this>
     */
    public function priority(): BelongsTo
    {
        return $this->belongsTo(IncidentPriority::class, 'priority_id');
    }

    /**
     * @return BelongsTo<IncidentStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(IncidentStatus::class, 'status_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporterUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_id');
    }

    /**
     * @return BelongsTo<IncidentReporter, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(IncidentReporter::class, 'reporter_id');
    }

    /**
     * @return HasMany<IncidentAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(IncidentAssignment::class, 'incident_id');
    }

    /**
     * @return HasMany<IncidentTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(IncidentTask::class, 'incident_id');
    }

    /**
     * @return HasMany<IncidentComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(IncidentComment::class, 'incident_id');
    }

    /**
     * @return HasMany<IncidentAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(IncidentAttachment::class, 'incident_id');
    }

    /**
     * @return HasMany<IncidentEscalation, $this>
     */
    public function escalations(): HasMany
    {
        return $this->hasMany(IncidentEscalation::class, 'incident_id');
    }

    /**
     * @return HasMany<IncidentReminder, $this>
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(IncidentReminder::class, 'incident_id');
    }

    /**
     * @return HasMany<IncidentProgressReport, $this>
     */
    public function progressReports(): HasMany
    {
        return $this->hasMany(IncidentProgressReport::class, 'incident_id');
    }

    /**
     * @return HasOne<IncidentSla, $this>
     */
    public function sla(): HasOne
    {
        return $this->hasOne(IncidentSla::class, 'incident_id');
    }

    /**
     * @param  Builder<Incident>  $query
     * @return Builder<Incident>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('incidents.view')) {
            return $query;
        }

        return $query->where(function (Builder $subQuery) use ($user): void {
            $subQuery->where('reported_by_id', $user->id)
                ->orWhere('assigned_to_id', $user->id)
                ->orWhereHas('assignments', function (Builder $assignmentQuery) use ($user): void {
                    $assignmentQuery->where('assigned_to_id', $user->id);
                })
                ->orWhereHas('comments', function (Builder $commentQuery) use ($user): void {
                    $commentQuery->where('user_id', $user->id);
                });
        });
    }

    protected static function booted(): void
    {
        self::creating(function (self $incident): void {
            if (empty($incident->reference_code)) {
                $incident->reference_code = $incident->generateReferenceCode();
            }
        });
    }

    /**
     * @return Factory<static>
     */
    protected static function newFactory(): Factory
    {
        return IncidentFactory::new();
    }

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
