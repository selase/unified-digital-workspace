<?php

declare(strict_types=1);

namespace App\Modules\DocumentManagement\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DocumentQuizQuestion extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $connection = 'landlord';

    protected $fillable = [
        'quiz_id',
        'body',
        'options',
        'correct_option',
        'points',
        'sort_order',
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
            'options' => 'array',
        ];
    }
}
