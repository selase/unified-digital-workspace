<?php

declare(strict_types=1);

namespace App\Modules\Forms\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FormSubmission extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'form_submissions';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'form_definition_id',
        'user_id',
        'ip_address',
        'responses_json',
        'status',
        'reviewer_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    /**
     * @return BelongsTo<FormDefinition, $this>
     */
    public function formDefinition(): BelongsTo
    {
        return $this->belongsTo(FormDefinition::class, 'form_definition_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'uuid');
    }

    protected function casts(): array
    {
        return [
            'responses_json' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }
}
