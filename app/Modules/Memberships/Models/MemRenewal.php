<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Models;

use App\Modules\Finance\Models\FinInvoice;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One dues period for a member and the Finance invoice that collects it. */
final class MemRenewal extends Model
{
    use BelongsToTenant;

    public const BILLED = 'billed';

    public const PAID = 'paid';

    public const VOID = 'void';

    protected $table = 'mem_renewals';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @return BelongsTo<MemMember, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(MemMember::class, 'member_id');
    }

    /** @return BelongsTo<FinInvoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FinInvoice::class, 'invoice_id');
    }

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'amount' => 'integer'];
    }
}
