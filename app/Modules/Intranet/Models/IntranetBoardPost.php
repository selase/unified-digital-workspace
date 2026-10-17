<?php

declare(strict_types=1);

namespace App\Modules\Intranet\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class IntranetBoardPost extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'intranet_board_posts';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'intranet_message_board_id',
        'author_id',
        'title',
        'content',
        'is_announcement',
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_announcement' => 'boolean',
            'likes_count' => 'integer',
        ];
    }
}
