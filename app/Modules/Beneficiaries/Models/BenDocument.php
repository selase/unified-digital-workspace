<?php

declare(strict_types=1);

namespace App\Modules\Beneficiaries\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A photo or paper (ID copy, signed consent form, referral letter) kept with a beneficiary. Shown only to staff who may see personal details. */
final class BenDocument extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const KINDS = ['photo' => 'Photo', 'id' => 'ID document', 'consent_form' => 'Signed consent form', 'referral' => 'Referral letter', 'other' => 'Other'];

    protected $table = 'ben_documents';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @return BelongsTo<BenBeneficiary, $this> */
    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(BenBeneficiary::class, 'beneficiary_id');
    }

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }
}
