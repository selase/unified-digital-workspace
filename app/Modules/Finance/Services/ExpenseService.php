<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\FinContact;
use App\Modules\Finance\Models\FinExpense;
use App\Modules\Finance\Models\FinJournal;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Costs paid on the spot: debit the expense account, credit the account the money left from. */
final class ExpenseService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly NumberSequence $numbers,
        private readonly FinanceSettings $settings,
        private readonly TaxCalculator $taxes,
        private readonly ChartOfAccounts $accounts,
    ) {}

    /**
     * @param  array{description?: string|null, reference?: string|null, fund_id?: int|null, tax_id?: int|null}  $details
     */
    public function record(string $tenantId, int $amount, CarbonInterface $date, int $expenseAccountId, int $paidFromAccountId, ?FinContact $vendor = null, array $details = [], ?string $userId = null): FinExpense
    {
        if ($amount <= 0) {
            throw new DomainException('The expense amount must be greater than zero.');
        }

        return DB::connection('tenant')->transaction(function () use ($tenantId, $amount, $date, $expenseAccountId, $paidFromAccountId, $vendor, $details, $userId): FinExpense {
            $settings = $this->settings->for($tenantId);
            $tax = $this->taxes->resolve($tenantId, $settings->tax_enabled, isset($details['tax_id']) ? (int) $details['tax_id'] : null, $amount);
            $gross = $amount + $tax['amount'];

            $expense = FinExpense::query()->create([
                'tenant_id' => $tenantId,
                'number' => $this->numbers->next($tenantId, 'expense', $this->settings->prefix($settings, 'expense')),
                'contact_id' => $vendor?->id,
                'date' => $date->toDateString(),
                'account_id' => $expenseAccountId,
                'paid_from_account_id' => $paidFromAccountId,
                'fund_id' => $details['fund_id'] ?? null,
                'amount' => $gross,
                'tax_id' => $tax['tax_id'],
                'tax_amount' => $tax['amount'],
                'description' => $details['description'] ?? null,
                'reference' => $details['reference'] ?? null,
                'created_by' => $userId,
            ]);

            $journal = $this->ledger->post(
                $tenantId,
                $date,
                array_values(array_filter([
                    ['account_id' => $expenseAccountId, 'debit' => $amount, 'memo' => $details['description'] ?? $expense->number, 'contact_id' => $vendor?->id, 'fund_id' => $details['fund_id'] ?? null],
                    $tax['amount'] > 0 ? ['account_id' => $this->accounts->inputTaxAccount($tenantId)->id, 'debit' => $tax['amount'], 'memo' => 'Input tax '.$expense->number, 'contact_id' => $vendor?->id] : null,
                    ['account_id' => $paidFromAccountId, 'credit' => $gross, 'memo' => $expense->number, 'contact_id' => $vendor?->id],
                ])),
                'Expense '.$expense->number,
                'expense',
                $expense->id,
                $userId,
            );
            $expense->forceFill(['journal_id' => $journal->id])->save();

            return $expense;
        });
    }

    public function void(FinExpense $expense, ?string $userId = null): void
    {
        if ($expense->voided_at !== null) {
            throw new DomainException('This expense is already void.');
        }

        DB::connection('tenant')->transaction(function () use ($expense, $userId): void {
            $journal = FinJournal::query()->find($expense->journal_id);
            if ($journal instanceof FinJournal) {
                $this->ledger->reverse($journal, null, $userId);
            }

            $expense->forceFill(['voided_at' => now()])->save();
        });
    }
}
