<?php

declare(strict_types=1);

namespace App\Modules\Forums\Models;

use App\Modules\Forums\Database\Factories\ForumThreadFactory;
use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use App\Traits\SpatieActivityLogs;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $channel_id
 * @property string $title
 * @property string $slug
 * @property string $user_id
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $pinned_at
 * @property \Illuminate\Support\Carbon|null $locked_at
 * @property array<int, string>|null $tags
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @use HasFactory<ForumThreadFactory>
 */
final class ForumThread extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuid;
    use SpatieActivityLogs;

    public const string STATUS_OPEN = 'open';

    public const string STATUS_LOCKED = 'locked';

    public const string STATUS_FLAGGED = 'flagged';

    public const string STATUS_DELETED = 'deleted';

    public const array STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_LOCKED,
        self::STATUS_FLAGGED,
        self::STATUS_DELETED,
    ];

    protected $table = 'forum_threads';

    protected $fillable = [
        'tenant_id',
        'uuid',
        'channel_id',
        'title',
        'slug',
        'user_id',
        'status',
        'pinned_at',
        'locked_at',
        'tags',
        'metadata',
    ];

    public static function boot(): void
    {
        parent::boot();

        self::creating(function (ForumThread $thread): void {
            if (! $thread->slug) {
                $thread->slug = Str::slug($thread->title);
            }

            $thread->slug = Str::lower((string) $thread->slug);

            if (! $thread->status) {
                $thread->status = self::STATUS_OPEN;
            }
        });
    }

    /**
     * @return BelongsTo<ForumChannel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(ForumChannel::class, 'channel_id');
    }

    /**
     * @return HasMany<ForumPost, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(ForumPost::class, 'thread_id');
    }

    /**
     * @return HasMany<ForumModerationLog, $this>
     */
    public function moderationLogs(): HasMany
    {
        return $this->hasMany(ForumModerationLog::class, 'thread_id');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return Factory<static>
     */
    protected static function newFactory(): Factory
    {
        return ForumThreadFactory::new();
    }

    protected function casts(): array
    {
        return [
            'pinned_at' => 'datetime',
            'locked_at' => 'datetime',
            'tags' => 'array',
            'metadata' => 'array',
        ];
    }
}
