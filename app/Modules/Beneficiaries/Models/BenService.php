<?php

declare(strict_types=1);

namespace App\Modules\Beneficiaries\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BenService extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'ben_services';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @return BelongsTo<BenBeneficiary, $this> */
    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(BenBeneficiary::class, 'beneficiary_id');
    }

    protected function casts(): array
    {
        return ['delivered_on' => 'date', 'value' => 'integer'];
    }
}
