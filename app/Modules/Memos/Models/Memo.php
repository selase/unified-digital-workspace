<?php

declare(strict_types=1);

namespace App\Modules\Memos\Models;

use App\Models\User;
use App\Modules\Memos\Database\Factories\MemoFactory;
use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
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
 * @property string $status
 * @property string|null $signature_disk
 * @property string|null $signature_path
 * @property string|null $signature_filename
 * @property string|null $signature_mime_type
 * @property int|null $signature_size_bytes
 * @property \Illuminate\Support\Carbon|null $signed_at
 * @property string|null $verification_code_hash
 * @property \Illuminate\Support\Carbon|null $verification_sent_at
 * @property \Illuminate\Support\Carbon|null $verification_expires_at
 * @property int $verification_attempts
 * @property \Illuminate\Support\Carbon|null $verified_at
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @use HasFactory<MemoFactory>
 */
final class Memo extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuid;

    public const string STATUS_DRAFT = 'draft';
    public const string STATUS_PENDING = 'pending_verification';
    public const string STATUS_SENT = 'sent';
    public const string STATUS_ACKNOWLEDGED = 'acknowledged';
    public const string STATUS_CLOSED = 'closed';

    protected $table = 'memos';

    protected $fillable = [
        'tenant_id',
        'sender_id',
        'subject',
        'body',
        'status',
        'signature_disk',
        'signature_path',
        'signature_filename',
        'signature_mime_type',
        'signature_size_bytes',
        'signed_at',
        'verification_code_hash',
        'verification_sent_at',
        'verification_expires_at',
        'verification_attempts',
        'verified_at',
        'sent_at',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * @return HasMany<MemoRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(MemoRecipient::class);
    }

    /**
     * @return HasMany<MemoMinute, $this>
     */
    public function minutes(): HasMany
    {
        return $this->hasMany(MemoMinute::class);
    }

    /**
     * @return HasMany<MemoAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(MemoAction::class);
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
        return MemoFactory::new();
    }

    protected function casts(): array
    {
        return [
            'signed_at' => 'datetime',
            'verification_sent_at' => 'datetime',
            'verification_expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'sent_at' => 'datetime',
            'signature_size_bytes' => 'integer',
            'verification_attempts' => 'integer',
        ];
    }
}
