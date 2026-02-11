<?php

declare(strict_types=1);

namespace App\Modules\Memos\Models;

use App\Modules\Memos\Database\Factories\MemoActionFactory;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $memo_id
 * @property string $tenant_id
 * @property string $title
 * @property string|null $description
 * @property string|null $assigned_to_id
 * @property \Illuminate\Support\Carbon|null $due_at
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @use HasFactory<MemoActionFactory>
 */
final class MemoAction extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'memo_actions';

    protected $fillable = [
        'memo_id',
        'tenant_id',
        'title',
        'description',
        'assigned_to_id',
        'due_at',
        'status',
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
        return MemoActionFactory::new();
    }

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
        ];
    }
}
