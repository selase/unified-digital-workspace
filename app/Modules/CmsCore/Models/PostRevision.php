<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $post_id
 * @property string $user_id
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt
 * @property string $body
 * @property \Illuminate\Support\Carbon $created_at
 */
final class PostRevision extends Model
{
    public $timestamps = false;

    protected $table = 'post_revisions';

    protected $connection = 'landlord';

    protected $fillable = [
        'post_id',
        'user_id',
        'title',
        'slug',
        'excerpt',
        'body',
        'created_at',
    ];

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
