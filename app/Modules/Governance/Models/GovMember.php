<?php

declare(strict_types=1);

namespace App\Modules\Governance\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

final class GovMember extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const POSITIONS = ['Chair', 'Vice chair', 'Secretary', 'Treasurer', 'Member', 'Ex officio'];

    protected $table = 'gov_members';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'active', 'position' => 'Member', 'body' => 'Board'];

    protected function casts(): array
    {
        return ['term_starts_on' => 'date', 'term_ends_on' => 'date'];
    }
}
