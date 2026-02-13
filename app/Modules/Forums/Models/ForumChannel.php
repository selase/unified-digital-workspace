<?php

declare(strict_types=1);

namespace App\Modules\Forums\Models;

use App\Modules\Forums\Database\Factories\ForumChannelFactory;
use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use App\Traits\SpatieActivityLogs;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property array<string, mixed>|null $visibility
 * @property bool $is_locked
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @use HasFactory<ForumChannelFactory>
 */
final class ForumChannel extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuid;
    use SpatieActivityLogs;

    protected $table = 'forum_channels';

    protected $fillable = [
        'tenant_id',
        'uuid',
        'name',
        'slug',
        'description',
        'visibility',
        'is_locked',
        'sort_order',
    ];

    public static function boot(): void
    {
        parent::boot();

        self::creating(function (ForumChannel $channel): void {
            if (! $channel->slug) {
                $channel->slug = Str::slug($channel->name);
            }

            $channel->slug = Str::lower((string) $channel->slug);
        });
    }

    /**
     * @return HasMany<ForumThread, $this>
     */
    public function threads(): HasMany
    {
        return $this->hasMany(ForumThread::class, 'channel_id');
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
        return ForumChannelFactory::new();
    }

    protected function casts(): array
    {
        return [
            'visibility' => 'array',
            'is_locked' => 'boolean',
        ];
    }
}
