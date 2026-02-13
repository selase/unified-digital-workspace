<?php

declare(strict_types=1);

namespace App\Modules\Forums\Models;

use App\Models\User;
use App\Modules\Forums\Database\Factories\ForumPostFactory;
use App\Traits\BelongsToTenant;
use App\Traits\SpatieActivityLogs;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $thread_id
 * @property string $user_id
 * @property int|null $parent_id
 * @property string $body
 * @property bool $is_best_answer
 * @property \Illuminate\Support\Carbon|null $edited_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @use HasFactory<ForumPostFactory>
 */
final class ForumPost extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SpatieActivityLogs;

    protected $table = 'forum_posts';

    protected $fillable = [
        'tenant_id',
        'thread_id',
        'user_id',
        'parent_id',
        'body',
        'is_best_answer',
        'edited_at',
    ];

    /**
     * @return BelongsTo<ForumThread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(ForumThread::class, 'thread_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<ForumPost, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<ForumPost, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<ForumReaction, $this>
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(ForumReaction::class, 'post_id');
    }

    /**
     * @return HasMany<ForumModerationLog, $this>
     */
    public function moderationLogs(): HasMany
    {
        return $this->hasMany(ForumModerationLog::class, 'post_id');
    }

    /**
     * @return Factory<static>
     */
    protected static function newFactory(): Factory
    {
        return ForumPostFactory::new();
    }

    protected function casts(): array
    {
        return [
            'is_best_answer' => 'boolean',
            'edited_at' => 'datetime',
        ];
    }
}
