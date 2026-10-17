<?php

declare(strict_types=1);

namespace App\Modules\DocumentManagement\Models;

use App\Models\User;
use App\Modules\DocumentManagement\Models\Concerns\HasDocumentUuid;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $document_id
 * @property string $user_id
 * @property \Illuminate\Support\Carbon $acknowledged_at
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Document $document
 * @property-read User|null $user
 */
final class DocumentAcknowledgement extends Model
{
    use BelongsToTenant;
    use HasDocumentUuid;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'document_id',
        'user_id',
        'acknowledged_at',
        'notes',
    ];

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
    }

    protected function casts(): array
    {
        return [
            'acknowledged_at' => 'datetime',
        ];
    }
}
