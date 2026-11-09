<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\FinBill;
use App\Modules\Finance\Models\FinBillLine;
use App\Modules\Finance\Models\FinBillPayment;
use App\Modules\Finance\Models\FinContact;
use App\Modules\Finance\Models\FinJournal;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Supplier bills and what is paid against them. Approving a bill debits each
 * line's expense account and credits accounts payable; paying it debits payable
 * and credits the account the money left from.
 */
final class BillService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly NumberSequence $numbers,
        private readonly FinanceSettings $settings,
        private readonly ChartOfAccounts $accounts,
    ) {}

    /**
     * @param  array{issue_date: string, due_date?: string|null, vendor_reference?: string|null, notes?: string|null}  $data
     * @param  list<array{description: string, amount: int, account_id: int, fund_id?: int|null}>  $lines
     */
    public function saveDraft(string $tenantId, FinContact $vendor, array $data, array $lines, ?FinBill $bill = null, ?string $userId = null): FinBill
    {
        if ($bill instanceof FinBill && $bill->status !== FinBill::STATUS_DRAFT) {
            throw new DomainException('Only draft bills can be edited.');
        }

        if ($lines === []) {
            throw new DomainException('Add at least one line.');
        }

        $settings = $this->settings->for($tenantId);

        return DB::connection('tenant')->transaction(function () use ($tenantId, $vendor, $data, $lines, $bill, $userId, $settings): FinBill {
            $bill ??= new FinBill(['tenant_id' => $tenantId, 'status' => FinBill::STATUS_DRAFT, 'created_by' => $userId]);

            $bill->fill([
                'contact_id' => $vendor->id,
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'] ?? null,
                'vendor_reference' => $data['vendor_reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'currency' => $settings->base_currency,
                'total' => array_sum(array_column($lines, 'amount')),
            ])->save();

            FinBillLine::query()->where('bill_id', $bill->id)->delete();
            foreach (array_values($lines) as $position => $line) {
                FinBillLine::query()->create([
                    'bill_id' => $bill->id,
                    'position' => $position,
                    'description' => $line['description'],
                    'amount' => $line['amount'],
                    'account_id' => $line['account_id'],
                    'fund_id' => $line['fund_id'] ?? null,
                ]);
            }

            return $bill->load('lines');
        });
    }

    public function approve(FinBill $bill, ?string $userId = null): FinBill
    {
        if ($bill->status !== FinBill::STATUS_DRAFT) {
            throw new DomainException('Only a draft bill can be approved.');
        }

        $bill->load(['lines', 'contact']);
        if ($bill->total <= 0) {
            throw new DomainException('A bill with a zero total cannot be approved.');
        }

        $tenantId = $bill->tenant_id;
        $settings = $this->settings->for($tenantId);

        return DB::connection('tenant')->transaction(function () use ($bill, $tenantId, $settings, $userId): FinBill {
            $bill->number = $this->numbers->next($tenantId, 'bill', $this->settings->prefix($settings, 'bill'));
            $terms = $bill->contact->payment_terms_days ?? $settings->default_payment_terms_days;
            $bill->due_date ??= $bill->issue_date->copy()->addDays($terms);
            $bill->status = FinBill::STATUS_APPROVED;
            $bill->approved_at = now();

            $journalLines = $bill->lines->map(fn (FinBillLine $line): array => [
                'account_id' => $line->account_id,
                'debit' => $line->amount,
                'memo' => $line->description,
                'contact_id' => $bill->contact_id,
                'fund_id' => $line->fund_id,
            ])->all();
            $journalLines[] = [
                'account_id' => $this->accounts->bySystemKey($tenantId, ChartOfAccounts::PAYABLE)->id,
                'credit' => $bill->total,
                'memo' => $bill->number,
                'contact_id' => $bill->contact_id,
            ];

            $journal = $this->ledger->post($tenantId, $bill->issue_date, $journalLines, 'Bill '.$bill->number, 'bill', $bill->id, $userId);
            $bill->journal_id = $journal->id;
            $bill->save();

            return $bill;
        });
    }

    /** Reverses the bill's journal. A bill with active payments must have them voided first. */
    public function void(FinBill $bill, ?string $userId = null): FinBill
    {
        if ($bill->status === FinBill::STATUS_VOID) {
            throw new DomainException('This bill is already void.');
        }

        if ($bill->payments()->whereNull('voided_at')->exists()) {
            throw new DomainException('A bill with payments cannot be voided. Void the payments first.');
        }

        return DB::connection('tenant')->transaction(function () use ($bill, $userId): FinBill {
            $journal = FinJournal::query()->find($bill->journal_id);
            if ($journal instanceof FinJournal) {
                $this->ledger->reverse($journal, null, $userId);
            }

            $bill->forceFill(['status' => FinBill::STATUS_VOID, 'voided_at' => now()])->save();

            return $bill;
        });
    }

    /**
     * @param  array{method?: string, reference?: string|null, notes?: string|null}  $details
     */
    public function pay(FinBill $bill, int $amount, CarbonInterface $date, int $paidFromAccountId, array $details = [], ?string $userId = null): FinBillPayment
    {
        return DB::connection('tenant')->transaction(function () use ($bill, $amount, $date, $paidFromAccountId, $details, $userId): FinBillPayment {
            $locked = FinBill::query()->where('tenant_id', $bill->tenant_id)->lockForUpdate()->findOrFail($bill->id);

            if (! $locked->isOpen()) {
                throw new DomainException('Only an approved bill with a balance can be paid.');
            }
            if ($amount <= 0 || $amount > $locked->balanceDue()) {
                throw new DomainException('The payment must be more than zero and no more than the balance due.');
            }

            $tenantId = $locked->tenant_id;
            $settings = $this->settings->for($tenantId);

            $payment = FinBillPayment::query()->create([
                'tenant_id' => $tenantId,
                'number' => $this->numbers->next($tenantId, 'bill_payment', $this->settings->prefix($settings, 'bill_payment')),
                'bill_id' => $locked->id,
                'contact_id' => $locked->contact_id,
                'date' => $date->toDateString(),
                'amount' => $amount,
                'method' => $details['method'] ?? 'bank_transfer',
                'reference' => $details['reference'] ?? null,
                'paid_from_account_id' => $paidFromAccountId,
                'notes' => $details['notes'] ?? null,
                'created_by' => $userId,
            ]);

            $journal = $this->ledger->post(
                $tenantId,
                $date,
                [
                    ['account_id' => $this->accounts->bySystemKey($tenantId, ChartOfAccounts::PAYABLE)->id, 'debit' => $amount, 'memo' => $payment->number, 'contact_id' => $locked->contact_id],
                    ['account_id' => $paidFromAccountId, 'credit' => $amount, 'memo' => $payment->number, 'contact_id' => $locked->contact_id],
                ],
                'Payment '.$payment->number.' for bill '.$locked->number,
                'bill_payment',
                $payment->id,
                $userId,
            );
            $payment->forceFill(['journal_id' => $journal->id])->save();

            $this->refreshPaymentStatus($locked);

            return $payment;
        });
    }

    public function voidPayment(FinBillPayment $payment, ?string $userId = null): void
    {
        if ($payment->voided_at !== null) {
            throw new DomainException('This payment is already void.');
        }

        DB::connection('tenant')->transaction(function () use ($payment, $userId): void {
            $journal = FinJournal::query()->find($payment->journal_id);
            if ($journal instanceof FinJournal) {
                $this->ledger->reverse($journal, null, $userId);
            }

            $payment->forceFill(['voided_at' => now()])->save();
            $this->refreshPaymentStatus($payment->bill()->firstOrFail());
        });
    }

    private function refreshPaymentStatus(FinBill $bill): void
    {
        $paid = (int) $bill->payments()->whereNull('voided_at')->sum('amount');

        $bill->forceFill([
            'amount_paid' => $paid,
            'status' => match (true) {
                $paid >= $bill->total => FinBill::STATUS_PAID,
                $paid > 0 => FinBill::STATUS_PARTLY_PAID,
                default => FinBill::STATUS_APPROVED,
            },
        ])->save();
    }
}
