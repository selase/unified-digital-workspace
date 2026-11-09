<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A supplier's invoice the organisation owes. Posts to the ledger when approved. */
final class FinBill extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PARTLY_PAID = 'partly_paid';

    public const STATUS_PAID = 'paid';

    public const STATUS_VOID = 'void';

    public const STATUS_APPLIED = 'applied';

    public const TYPE_BILL = 'bill';

    public const TYPE_CREDIT = 'credit';

    protected $table = 'fin_bills';

    /** @var list<string> */
    protected $guarded = ['id'];

    public function balanceDue(): int
    {
        return max(0, $this->total - $this->amount_paid - $this->credits_applied);
    }

    public function isCredit(): bool
    {
        return $this->type === self::TYPE_CREDIT;
    }

    public function isOpen(): bool
    {
        return ! $this->isCredit() && in_array($this->status, [self::STATUS_APPROVED, self::STATUS_PARTLY_PAID], true);
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_date !== null && $this->due_date->lt(today());
    }

    /** @return BelongsTo<FinContact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(FinContact::class, 'contact_id');
    }

    /** @return HasMany<FinBillLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(FinBillLine::class, 'bill_id')->orderBy('position');
    }

    /** @return HasMany<FinBillPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(FinBillPayment::class, 'bill_id');
    }

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'approved_at' => 'datetime',
            'voided_at' => 'datetime',
            'total' => 'integer',
            'amount_paid' => 'integer',
            'credits_applied' => 'integer',
        ];
    }
}
