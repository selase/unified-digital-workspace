<?php

declare(strict_types=1);

namespace App\Modules\Donations\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class DonDonor extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'don_donors';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @return HasMany<DonDonation, $this> */
    public function donations(): HasMany
    {
        return $this->hasMany(DonDonation::class, 'donor_id');
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
