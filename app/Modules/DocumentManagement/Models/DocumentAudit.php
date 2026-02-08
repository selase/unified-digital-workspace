<?php

declare(strict_types=1);

namespace App\Modules\DocumentManagement\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DocumentAudit extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $connection;

    protected $fillable = [
        'tenant_id',
        'document_id',
        'user_id',
        'event',
        'metadata',
        'created_at',
    ];

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
