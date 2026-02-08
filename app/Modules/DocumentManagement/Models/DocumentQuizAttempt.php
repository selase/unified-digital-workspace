<?php

declare(strict_types=1);

namespace App\Modules\DocumentManagement\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DocumentQuizAttempt extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $connection;

    protected $fillable = [
        'tenant_id',
        'quiz_id',
        'user_id',
        'score',
        'responses',
        'started_at',
        'completed_at',
    ];

    /**
     * @return BelongsTo<DocumentQuiz, $this>
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(DocumentQuiz::class, 'quiz_id');
    }

    protected function casts(): array
    {
        return [
            'responses' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
