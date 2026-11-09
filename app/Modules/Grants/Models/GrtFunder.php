<?php

declare(strict_types=1);

namespace App\Modules\Grants\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class GrtFunder extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const TYPES = ['foundation' => 'Foundation', 'government' => 'Government', 'corporate' => 'Corporate', 'multilateral' => 'Multilateral / UN', 'individual' => 'Individual'];

    protected $table = 'grt_funders';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @return HasMany<GrtGrant, $this> */
    public function grants(): HasMany
    {
        return $this->hasMany(GrtGrant::class, 'funder_id');
    }
}
