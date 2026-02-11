<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $post_id
 * @property string $key
 * @property array<string, mixed>|string $value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class PostMeta extends Model
{
    use UsesTenantConnection;

    protected $table = 'post_meta';

    protected $fillable = [
        'post_id',
        'key',
        'value',
    ];

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id');
    }

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }
}
