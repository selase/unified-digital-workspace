<?php

declare(strict_types=1);

namespace App\Modules\DocumentManagement\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $document_id
 * @property string $title
 * @property string|null $description
 * @property array<string, mixed>|null $settings
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Document|null $document
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DocumentQuizQuestion> $questions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DocumentQuizAttempt> $attempts
 */
final class DocumentQuiz extends Model
{
    use BelongsToTenant;

    protected $connection;

    protected $fillable = [
        'tenant_id',
        'document_id',
        'title',
        'description',
        'settings',
    ];

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return HasMany<DocumentQuizQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(DocumentQuizQuestion::class, 'quiz_id');
    }

    /**
     * @return HasMany<DocumentQuizAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(DocumentQuizAttempt::class, 'quiz_id');
    }

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }
}
