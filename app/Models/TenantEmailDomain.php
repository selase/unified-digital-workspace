<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A client's own domain that the platform sends mail as, and the mailbox provider that receives its mail.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $domain
 * @property string $mailbox_provider google, zoho or other
 * @property string $from_address
 * @property string $mail_from_domain
 * @property string $status pending, verified or failed
 * @property list<string>|null $dkim_tokens
 * @property string|null $mailbox_dkim_value TXT value of the provider's DKIM key, supplied by the client's admin
 */
final class TenantEmailDomain extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const PENDING = 'pending';

    public const VERIFIED = 'verified';

    public const FAILED = 'failed';

    protected $connection = 'landlord';

    protected $fillable = [
        'tenant_id',
        'domain',
        'mailbox_provider',
        'from_address',
        'mail_from_domain',
        'status',
        'dkim_tokens',
        'mailbox_dkim_value',
        'verified_at',
        'last_checked_at',
    ];

    public function isVerified(): bool
    {
        return $this->status === self::VERIFIED;
    }

    protected function casts(): array
    {
        return [
            'dkim_tokens' => 'array',
            'verified_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }
}
