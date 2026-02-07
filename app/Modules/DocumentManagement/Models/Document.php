<?php

declare(strict_types=1);

namespace App\Modules\DocumentManagement\Models;

use App\Modules\DocumentManagement\Models\Concerns\HasDocumentUuid;
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

    protected $connection = 'landlord';

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
        });
    }

    /**
     * @param  Builder<Document>  $query
     */
    public function scopeVisibleTo(Builder $query, string $userId): void
    {
        $query->where(function (Builder $q) use ($userId): void {
            $q->where('owner_id', $userId)
                ->orWhereJsonContains('visibility->users', $userId)
                ->orWhere('visibility->tenant_wide', true)
                ->orWhereNull('visibility');
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
}
