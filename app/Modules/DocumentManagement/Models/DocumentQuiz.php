<?php

declare(strict_types=1);

namespace App\Modules\DocumentManagement\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DocumentQuiz extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $connection = 'landlord';

    protected $fillable = [
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
