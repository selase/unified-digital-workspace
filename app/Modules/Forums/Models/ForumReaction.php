<?php

declare(strict_types=1);

namespace App\Modules\Forums\Models;

use App\Models\User;
use App\Modules\Forums\Database\Factories\ForumReactionFactory;
use App\Traits\BelongsToTenant;
use App\Traits\SpatieActivityLogs;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $post_id
 * @property string $user_id
 * @property string $type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @use HasFactory<ForumReactionFactory>
 */
final class ForumReaction extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SpatieActivityLogs;

    protected $table = 'forum_reactions';

    protected $fillable = [
        'tenant_id',
        'post_id',
        'user_id',
        'type',
    ];

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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return Factory<static>
     */
    protected static function newFactory(): Factory
    {
        return ForumReactionFactory::new();
    }
}
