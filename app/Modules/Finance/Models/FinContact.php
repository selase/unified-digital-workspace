<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class FinContact extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'fin_contacts';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @return HasMany<FinInvoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(FinInvoice::class, 'contact_id');
    }

    protected function casts(): array
    {
        return ['is_customer' => 'boolean', 'is_vendor' => 'boolean', 'is_active' => 'boolean'];
    }
}
