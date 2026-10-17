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
        return $this->belongsTo(User::class, 'sender_id', 'uuid');
    }

    /**
     * @return HasMany<ForumMessageRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(ForumMessageRecipient::class, 'message_id');
    }

    /**
     * Return only recipients visible to $viewer (stripping BCC rows unless $viewer is the sender
     * or that specific BCC recipient).
     *
     * @return \Illuminate\Support\Collection<int, ForumMessageRecipient>
     */
    public function visibleRecipientsFor(?User $viewer): \Illuminate\Support\Collection
    {
        $all = $this->relationLoaded('recipients') ? $this->recipients : $this->recipients()->get();

        if ($viewer !== null && (string) $this->sender_id === (string) $viewer->uuid) {
            return $all->values();
        }

        return $all->filter(function (ForumMessageRecipient $recipient) use ($viewer): bool {
            if (($recipient->role ?? ForumMessageRecipient::ROLE_TO) !== ForumMessageRecipient::ROLE_BCC) {
                return true;
            }

            if ($viewer === null) {
                return false;
            }

            return (string) $recipient->user_id === (string) $viewer->uuid;
        })->values();
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
