<?php

declare(strict_types=1);

namespace App\Modules\Forums\Models;

use App\Models\User;
use App\Modules\Forums\Database\Factories\ForumModerationLogFactory;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int|null $thread_id
 * @property int|null $post_id
 * @property string $moderator_id
 * @property string $action
 * @property string|null $reason
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 *
 * @use HasFactory<ForumModerationLogFactory>
 */
final class ForumModerationLog extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public $timestamps = false;

    protected $table = 'forum_moderation_logs';

    protected $fillable = [
        'tenant_id',
        'thread_id',
        'post_id',
        'moderator_id',
        'action',
        'reason',
        'metadata',
        'created_at',
    ];

    /**
     * @return BelongsTo<ForumThread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(ForumThread::class, 'thread_id');
    }

    /**
     * @return BelongsTo<ForumPost, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(ForumPost::class, 'post_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }

    /**
     * @return Factory<static>
     */
    protected static function newFactory(): Factory
    {
        return ForumModerationLogFactory::new();
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
