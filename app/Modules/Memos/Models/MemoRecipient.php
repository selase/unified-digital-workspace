<?php

declare(strict_types=1);

namespace App\Modules\Memos\Models;

use App\Modules\Memos\Database\Factories\MemoRecipientFactory;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $memo_id
 * @property string $tenant_id
 * @property string $recipient_type
 * @property string|null $recipient_id
 * @property string $role
 * @property bool $requires_ack
 * @property \Illuminate\Support\Carbon|null $acknowledged_at
 * @property string|null $acknowledged_by_id
 * @property string|null $shared_by_id
 * @property \Illuminate\Support\Carbon|null $shared_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @use HasFactory<MemoRecipientFactory>
 */
final class MemoRecipient extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const string ROLE_TO = 'to';
    public const string ROLE_CC = 'cc';

    protected $table = 'memo_recipients';

    protected $fillable = [
        'memo_id',
        'tenant_id',
        'recipient_type',
        'recipient_id',
        'role',
        'requires_ack',
        'acknowledged_at',
        'acknowledged_by_id',
        'shared_by_id',
        'shared_at',
    ];

    /**
     * @return BelongsTo<Memo, $this>
     */
    public function memo(): BelongsTo
    {
        return $this->belongsTo(Memo::class);
    }

    /**
     * @return Factory<static>
     */
    protected static function newFactory(): Factory
    {
        return MemoRecipientFactory::new();
    }

    protected function casts(): array
    {
        return [
            'requires_ack' => 'boolean',
            'acknowledged_at' => 'datetime',
            'shared_at' => 'datetime',
        ];
    }
}
