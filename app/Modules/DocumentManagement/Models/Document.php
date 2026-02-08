<?php

declare(strict_types=1);

namespace App\Modules\DocumentManagement\Models;

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
use Illuminate\Support\Str;

final class Document extends Model
{
    use BelongsToTenant;
    use HasDocumentUuid;
    use HasFactory;

    public const STATUSES = ['draft', 'published', 'archived'];

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
    public function scopeVisibleTo(Builder $query, string $userId): void
    {
        $tenantScope = $this->teamId();
        $org = $this->resolveOrgScope($userId);

        $query->where(function (Builder $q) use ($userId, $tenantScope, $org): void {
            $q->where('owner_id', $userId)
                ->orWhereJsonContains('visibility->users', $userId)
                ->orWhereJsonContains('visibility->departments', $org['departments'])
                ->orWhereJsonContains('visibility->directorates', $org['directorates'])
                ->orWhereJsonContains('visibility->teams', $tenantScope)
                ->orWhere('visibility->tenant_wide', true)
                ->orWhereNull('visibility');
        });

        $query->where(function (Builder $q) use ($userId): void {
            $q->where('visibility->is_private', '!=', true)
                ->orWhereNull('visibility->is_private')
                ->orWhere(function (Builder $qq) use ($userId): void {
                    $qq->where('visibility->is_private', true)
                        ->where('owner_id', $userId);
                });
        });
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

    protected function casts(): array
    {
        return [
            'visibility' => 'array',
            'tags' => 'array',
            'metadata' => 'array',
            'published_at' => 'datetime',
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
    private function resolveOrgScope(string $userId): array
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

        $employee = Employee::query()
            ->where('user_id', $userId)
            ->first();

        if ($employee) {
            $departments = $employee->departmentTypes()->pluck('department_id')->filter()->values()->all();
            $directorates = $employee->directorates()->pluck('id')->filter()->values()->all();
        }

        return [
            'departments' => $departments,
            'directorates' => $directorates,
        ];
    }
}
