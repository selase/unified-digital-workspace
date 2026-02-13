<?php

declare(strict_types=1);

namespace App\Modules\Forums\Models;

use App\Models\User;
use App\Modules\Forums\Database\Factories\ForumMessageRecipientFactory;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $message_id
 * @property string $user_id
 * @property \Illuminate\Support\Carbon|null $read_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @use HasFactory<ForumMessageRecipientFactory>
 */
final class ForumMessageRecipient extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'forum_message_recipients';

    protected $fillable = [
        'tenant_id',
        'message_id',
        'user_id',
        'read_at',
        'deleted_at',
    ];

    /**
     * @return BelongsTo<ForumMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ForumMessage::class, 'message_id');
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
        return ForumMessageRecipientFactory::new();
    }

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
}
