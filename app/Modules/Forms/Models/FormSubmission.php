<?php

declare(strict_types=1);

namespace App\Modules\Forms\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $form_definition_id
 * @property string|null $user_id
 * @property string|null $ip_address
 * @property string|null $voter_hash
 * @property array<string, mixed> $responses_json
 * @property string $status
 * @property string|null $reviewer_notes
 * @property string|null $reviewed_by
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
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
        'voter_hash',
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
