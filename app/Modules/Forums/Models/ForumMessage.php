<?php

declare(strict_types=1);

namespace App\Modules\Forums\Models;

use App\Models\User;
use App\Modules\Forums\Database\Factories\ForumMessageFactory;
use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use App\Traits\SpatieActivityLogs;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property string $sender_id
 * @property string $subject
 * @property string $body
 * @property array<string, mixed>|null $visibility
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @use HasFactory<ForumMessageFactory>
 */
final class ForumMessage extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuid;
    use SpatieActivityLogs;

    protected $table = 'forum_messages';

    protected $fillable = [
        'tenant_id',
        'uuid',
        'sender_id',
        'subject',
        'body',
        'visibility',
        'metadata',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * @return HasMany<ForumMessageRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(ForumMessageRecipient::class, 'message_id');
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
        return ForumMessageFactory::new();
    }

    protected function casts(): array
    {
        return [
            'visibility' => 'array',
            'metadata' => 'array',
        ];
    }
}
