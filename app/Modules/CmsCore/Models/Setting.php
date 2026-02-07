<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Models;

use App\Modules\CmsCore\Models\Concerns\HasCmsUuid;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property string|null $group
 * @property string $key
 * @property array<string, mixed>|string $value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class Setting extends Model
{
    use BelongsToTenant;
    use HasCmsUuid;

    protected $table = 'settings';

    protected $connection = 'landlord';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'group',
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }
}
