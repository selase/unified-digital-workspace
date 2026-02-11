<?php

declare(strict_types=1);

namespace App\Modules\Memos\Models;

use App\Modules\Memos\Database\Factories\MemoMinuteFactory;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $memo_id
 * @property string $tenant_id
 * @property string $author_id
 * @property string $body
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @use HasFactory<MemoMinuteFactory>
 */
final class MemoMinute extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'memo_minutes';

    protected $fillable = [
        'memo_id',
        'tenant_id',
        'author_id',
        'body',
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
        return MemoMinuteFactory::new();
    }
}
