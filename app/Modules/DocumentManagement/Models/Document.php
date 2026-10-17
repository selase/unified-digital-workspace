<?php

declare(strict_types=1);

namespace App\Modules\DocumentManagement\Models;

use App\Models\User;
use App\Modules\DocumentManagement\Models\Concerns\HasDocumentUuid;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Services\Tenancy\TenantContext;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property string $status
 * @property array<string, mixed>|null $visibility
 * @property int|null $current_version_id
 * @property string $owner_id
 * @property string|null $reviewer_id
 * @property string|null $approver_id
 * @property \Illuminate\Support\Carbon|null $review_due_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property string|null $review_comments
 * @property int|null $supersedes_id
 * @property int|null $superseded_by_id
 * @property string|null $category
 * @property array<int, string>|null $tags
 * @property array<string, mixed>|null $metadata
 * @property \Carbon\CarbonInterface|\Illuminate\Support\Carbon|string|null $published_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User|null $owner
 * @property-read User|null $reviewer
 * @property-read User|null $approver
 * @property-read Document|null $supersedes
 * @property-read Document|null $supersededBy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DocumentAcknowledgement> $acknowledgements
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DocumentVersion> $versions
 * @property-read DocumentVersion|null $currentVersion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DocumentQuiz> $quizzes
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DocumentAudit> $audits
 */
final class Document extends Model
{
    use BelongsToTenant;
    use HasDocumentUuid;
    use HasFactory;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_IN_REVIEW = 'in_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_UNDER_REVISION = 'under_revision';

    public const STATUS_OBSOLETE = 'obsolete';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_IN_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_PUBLISHED,
        self::STATUS_UNDER_REVISION,
        self::STATUS_OBSOLETE,
        self::STATUS_ARCHIVED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_IN_REVIEW => 'In Review',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_PUBLISHED => 'Published / Effective',
        self::STATUS_UNDER_REVISION => 'Under Revision',
        self::STATUS_OBSOLETE => 'Obsolete / Superseded',
        self::STATUS_ARCHIVED => 'Archived',
    ];

    protected $connection;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'title',
        'slug',
        'description',
        'status',
        'visibility',
        'current_version_id',
        'owner_id',
        'reviewer_id',
        'approver_id',
        'review_due_at',
        'expires_at',
        'review_comments',
        'supersedes_id',
        'superseded_by_id',
        'category',
        'tags',
        'metadata',
        'published_at',
    ];

    public static function boot(): void
    {
        parent::boot();

        self::creating(function (Document $document): void {
            if (! $document->slug) {
                $document->slug = Str::slug($document->title);
            }

            if (! $document->tenant_id) {
                $tenantId = session('active_tenant_id') ?? app(TenantContext::class)->getTenant()?->id;
                $document->tenant_id = $tenantId;
            }
        });
    }

    /**
     * @param  Builder<Document>  $query
     */
    public function scopeVisibleTo(Builder $query, int|string $userId): void
    {
        $userKey = (string) $userId;
        $tenantScope = $this->teamId();
        $org = $this->resolveOrgScope($userId);

        $query->where(function (Builder $q) use ($userKey, $tenantScope, $org): void {
            $q->where('owner_id', $userKey)
                ->orWhereJsonContains('visibility->users', $userKey);

            foreach ($org['departments'] as $departmentId) {
                $q->orWhereJsonContains('visibility->departments', $departmentId);
            }

            foreach ($org['directorates'] as $directorateId) {
                $q->orWhereJsonContains('visibility->directorates', $directorateId);
            }

            if ($tenantScope !== '') {
                $q->orWhereJsonContains('visibility->teams', $tenantScope);
            }

            $q->orWhere('visibility->tenant_wide', true)
                ->orWhereNull('visibility');
        });

        $query->where(function (Builder $q) use ($userKey): void {
            $q->where('visibility->is_private', '!=', true)
                ->orWhereNull('visibility->is_private')
                ->orWhere(function (Builder $qq) use ($userKey): void {
                    $qq->where('visibility->is_private', true)
                        ->where('owner_id', $userKey);
                });
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id', 'uuid');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id', 'uuid');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id', 'uuid');
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }

    /**
     * @return HasMany<DocumentAcknowledgement, $this>
     */
    public function acknowledgements(): HasMany
    {
        return $this->hasMany(DocumentAcknowledgement::class);
    }

    /**
     * @return HasMany<DocumentVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    /**
     * @return HasOne<DocumentVersion, $this>
     */
    public function currentVersion(): HasOne
    {
        return $this->hasOne(DocumentVersion::class, 'id', 'current_version_id');
    }

    /**
     * @return HasMany<DocumentQuiz, $this>
     */
    public function quizzes(): HasMany
    {
        return $this->hasMany(DocumentQuiz::class);
    }

    /**
     * @return HasMany<DocumentAudit, $this>
     */
    public function audits(): HasMany
    {
        return $this->hasMany(DocumentAudit::class);
    }

    /**
     * @return BelongsTo<DocumentVersion, $this>
     */
    public function currentVersionRelation(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'current_version_id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isInReview(): bool
    {
        return $this->status === self::STATUS_IN_REVIEW;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isUnderRevision(): bool
    {
        return $this->status === self::STATUS_UNDER_REVISION;
    }

    public function isObsolete(): bool
    {
        return in_array($this->status, [self::STATUS_OBSOLETE, self::STATUS_ARCHIVED], true);
    }

    public function isReviewOverdue(): bool
    {
        return $this->isInReview() && $this->review_due_at !== null && $this->review_due_at->isPast();
    }

    public function isExpired(): bool
    {
        return $this->isPublished() && $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isAcknowledgedBy(string $userUuid): bool
    {
        return $this->acknowledgements()->where('user_id', $userUuid)->exists();
    }

    protected function casts(): array
    {
        return [
            'visibility' => 'array',
            'tags' => 'array',
            'metadata' => 'array',
            'published_at' => 'datetime',
            'review_due_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    private function teamId(): string
    {
        if (app()->bound('permissions.team_id')) {
            return (string) app('permissions.team_id');
        }

        return (string) session('active_tenant_id', '');
    }

    /**
     * @return array{departments: array<int|string>, directorates: array<int|string>}
     */
    private function resolveOrgScope(int|string $userId): array
    {
        $departments = [];
        $directorates = [];

        $tenantDriver = config('database.connections.tenant.driver');
        if ($tenantDriver === null) {
            return [
                'departments' => $departments,
                'directorates' => $directorates,
            ];
        }

        $tenantConnection = config('database.default_tenant_connection', 'tenant');
        if (! Schema::connection($tenantConnection)->hasTable('hrms_employees')) {
            return [
                'departments' => $departments,
                'directorates' => $directorates,
            ];
        }

        $lookupUserId = $userId;
        if (is_string($userId) && Str::isUuid($userId)) {
            $lookupUserId = User::query()
                ->where('uuid', $userId)
                ->value('id');
        }

        if (! $lookupUserId) {
            return [
                'departments' => $departments,
                'directorates' => $directorates,
            ];
        }

        $employee = Employee::query()
            ->where('user_id', $lookupUserId)
            ->first();

        if ($employee) {
            $departments = $employee->departmentTypes()->pluck('hrms_department_types.department_id')->filter()->values()->all();
            $directorates = $employee->directorates()->pluck('hrms_directorates.id')->filter()->values()->all();
        }

        return [
            'departments' => $departments,
            'directorates' => $directorates,
        ];
    }
}
