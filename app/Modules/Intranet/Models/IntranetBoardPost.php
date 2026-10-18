<?php

declare(strict_types=1);

namespace App\Modules\Intranet\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $intranet_message_board_id
 * @property int|null $parent_id
 * @property string $author_id
 * @property string $title
 * @property string $content
 * @property bool $is_announcement
 * @property bool $is_pinned
 * @property int $likes_count
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read IntranetMessageBoard $board
 * @property-read User|null $author
 * @property-read \Illuminate\Database\Eloquent\Collection<int, IntranetBoardPost> $replies
 * @property-read IntranetBoardPost|null $parent
 */
final class IntranetBoardPost extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'intranet_board_posts';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'intranet_message_board_id',
        'parent_id',
        'author_id',
        'title',
        'content',
        'is_announcement',
        'is_pinned',
        'likes_count',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<IntranetMessageBoard, $this>
     */
    public function board(): BelongsTo
    {
        return $this->belongsTo(IntranetMessageBoard::class, 'intranet_message_board_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id', 'uuid');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->oldest();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeTopLevel(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAnnouncements(Builder $query): Builder
    {
        return $query->where('is_announcement', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_announcement' => 'boolean',
            'is_pinned' => 'boolean',
            'likes_count' => 'integer',
            'parent_id' => 'integer',
        ];
    }
}
