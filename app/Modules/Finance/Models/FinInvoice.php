<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FinInvoice extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const TYPE_INVOICE = 'invoice';

    public const TYPE_QUOTE = 'quote';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_PARTLY_PAID = 'partly_paid';

    public const STATUS_PAID = 'paid';

    public const STATUS_VOID = 'void';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    protected $table = 'fin_invoices';

    /** @var list<string> */
    protected $guarded = ['id'];

    public function balanceDue(): int
    {
        return max(0, $this->total - $this->amount_paid);
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, [self::STATUS_SENT, self::STATUS_PARTLY_PAID], true)
            && $this->due_date !== null
            && $this->due_date->lt(today());
    }

    /** @return BelongsTo<FinContact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(FinContact::class, 'contact_id');
    }

    /** @return HasMany<FinInvoiceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(FinInvoiceLine::class, 'invoice_id')->orderBy('position');
    }

    /** @return HasMany<FinPaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(FinPaymentAllocation::class, 'invoice_id');
    }

    /** @return BelongsTo<FinJournal, $this> */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(FinJournal::class, 'journal_id');
    }

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'sent_at' => 'datetime',
            'voided_at' => 'datetime',
            'subtotal' => 'integer',
            'tax_total' => 'integer',
            'total' => 'integer',
            'amount_paid' => 'integer',
        ];
    }
}
