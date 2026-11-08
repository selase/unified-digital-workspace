<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\FinContact;
use App\Modules\Finance\Models\FinInvoice;
use App\Modules\Finance\Models\FinInvoiceLine;
use App\Modules\Finance\Models\FinTax;
use DomainException;
use Illuminate\Support\Facades\DB;

final class InvoiceService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly NumberSequence $numbers,
        private readonly FinanceSettings $settings,
        private readonly ChartOfAccounts $accounts,
    ) {}

    /**
     * @param  array{type?: string, issue_date: string, due_date?: string|null, notes?: string|null, terms?: string|null, recurring_id?: int|null}  $data
     * @param  list<array{description: string, quantity: float|int|string, unit_price: int, item_id?: int|null, account_id?: int|null, tax_id?: int|null, fund_id?: int|null}>  $lines
     */
    public function saveDraft(string $tenantId, FinContact $contact, array $data, array $lines, ?FinInvoice $invoice = null, ?string $userId = null): FinInvoice
    {
        if ($invoice instanceof FinInvoice && $invoice->status !== FinInvoice::STATUS_DRAFT) {
            throw new DomainException('Only draft documents can be edited.');
        }

        if ($lines === []) {
            throw new DomainException('Add at least one line.');
        }

        $settings = $this->settings->for($tenantId);

        return DB::connection('tenant')->transaction(function () use ($tenantId, $contact, $data, $lines, $invoice, $userId, $settings): FinInvoice {
            $invoice ??= new FinInvoice(['tenant_id' => $tenantId, 'status' => FinInvoice::STATUS_DRAFT, 'created_by' => $userId]);

            $invoice->fill([
                'type' => $data['type'] ?? ($invoice->exists ? $invoice->type : FinInvoice::TYPE_INVOICE),
                'contact_id' => $contact->id,
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'] ?? null,
                'currency' => $settings->base_currency,
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'recurring_id' => $data['recurring_id'] ?? ($invoice->exists ? $invoice->recurring_id : null),
            ]);
            $invoice->save();

            FinInvoiceLine::query()->where('invoice_id', $invoice->id)->delete();

            $subtotal = 0;
            $taxTotal = 0;

            foreach (array_values($lines) as $position => $line) {
                $quantity = (int) round(((float) $line['quantity']) * 1000);
                $lineTotal = (int) round($quantity * (int) $line['unit_price'] / 1000);
                $taxId = isset($line['tax_id']) ? (int) $line['tax_id'] : null;
                $taxAmount = $this->taxFor($tenantId, $settings->tax_enabled, $taxId, $lineTotal);

                FinInvoiceLine::query()->create([
                    'invoice_id' => $invoice->id,
                    'position' => $position,
                    'item_id' => $line['item_id'] ?? null,
                    'description' => $line['description'],
                    'quantity_thousandths' => $quantity,
                    'unit_price' => (int) $line['unit_price'],
                    'account_id' => $line['account_id'] ?? null,
                    'tax_id' => $taxAmount > 0 ? $taxId : null,
                    'fund_id' => $line['fund_id'] ?? null,
                    'line_total' => $lineTotal,
                    'tax_amount' => $taxAmount,
                ]);

                $subtotal += $lineTotal;
                $taxTotal += $taxAmount;
            }

            $invoice->forceFill([
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'total' => $subtotal + $taxTotal,
            ])->save();

            return $invoice->load('lines');
        });
    }

    /**
     * Numbers the document and, for invoices, posts it to the ledger:
     * debit receivables, credit income per line and the tax account.
     */
    public function send(FinInvoice $invoice, ?string $userId = null): FinInvoice
    {
        if ($invoice->status !== FinInvoice::STATUS_DRAFT) {
            throw new DomainException('Only a draft can be sent.');
        }

        $invoice->load(['lines', 'contact']);
        if ($invoice->total <= 0) {
            throw new DomainException('A document with a zero total cannot be sent.');
        }

        $tenantId = $invoice->tenant_id;
        $settings = $this->settings->for($tenantId);
        $isQuote = $invoice->type === FinInvoice::TYPE_QUOTE;

        return DB::connection('tenant')->transaction(function () use ($invoice, $tenantId, $settings, $isQuote, $userId): FinInvoice {
            $key = $isQuote ? 'quote' : 'invoice';
            $invoice->number = $this->numbers->next($tenantId, $key, $this->settings->prefix($settings, $key));
            $terms = $invoice->contact->payment_terms_days ?? $settings->default_payment_terms_days;
            $invoice->due_date ??= $invoice->issue_date->copy()->addDays($terms);
            $invoice->status = FinInvoice::STATUS_SENT;
            $invoice->sent_at = now();

            if (! $isQuote) {
                $journal = $this->ledger->post(
                    $tenantId,
                    $invoice->issue_date,
                    $this->journalLines($invoice),
                    'Invoice '.$invoice->number,
                    'invoice',
                    $invoice->id,
                    $userId,
                );
                $invoice->journal_id = $journal->id;
            }

            $invoice->save();

            return $invoice;
        });
    }

    public function convertQuote(FinInvoice $quote, ?string $userId = null): FinInvoice
    {
        if ($quote->type !== FinInvoice::TYPE_QUOTE || $quote->converted_invoice_id !== null) {
            throw new DomainException('Only an unconverted quote can be turned into an invoice.');
        }

        $quote->load(['contact', 'lines']);

        return DB::connection('tenant')->transaction(function () use ($quote, $userId): FinInvoice {
            $invoice = $this->saveDraft(
                $quote->tenant_id,
                $quote->contact,
                [
                    'type' => FinInvoice::TYPE_INVOICE,
                    'issue_date' => today()->toDateString(),
                    'notes' => $quote->notes,
                    'terms' => $quote->terms,
                ],
                $quote->lines->map(fn (FinInvoiceLine $line): array => [
                    'description' => $line->description,
                    'quantity' => $line->quantity_thousandths / 1000,
                    'unit_price' => $line->unit_price,
                    'item_id' => $line->item_id,
                    'account_id' => $line->account_id,
                    'tax_id' => $line->tax_id,
                    'fund_id' => $line->fund_id,
                ])->all(),
                null,
                $userId,
            );

            $quote->forceFill(['status' => FinInvoice::STATUS_ACCEPTED, 'converted_invoice_id' => $invoice->id])->save();

            return $invoice;
        });
    }

    public function void(FinInvoice $invoice, ?string $userId = null): FinInvoice
    {
        if (in_array($invoice->status, [FinInvoice::STATUS_VOID, FinInvoice::STATUS_PAID], true) || $invoice->amount_paid > 0) {
            throw new DomainException('An invoice with payments cannot be voided. Void the payments first.');
        }

        return DB::connection('tenant')->transaction(function () use ($invoice, $userId): FinInvoice {
            if ($invoice->journal_id !== null) {
                $this->ledger->reverse($invoice->journal()->firstOrFail(), null, $userId);
            }

            $invoice->forceFill(['status' => FinInvoice::STATUS_VOID, 'voided_at' => now()])->save();

            return $invoice;
        });
    }

    public function refreshPaymentStatus(FinInvoice $invoice): FinInvoice
    {
        if (in_array($invoice->status, [FinInvoice::STATUS_VOID, FinInvoice::STATUS_DRAFT], true)) {
            return $invoice;
        }

        $paid = (int) $invoice->allocations()->sum('amount');
        $invoice->amount_paid = $paid;
        $invoice->status = match (true) {
            $paid >= $invoice->total => FinInvoice::STATUS_PAID,
            $paid > 0 => FinInvoice::STATUS_PARTLY_PAID,
            default => FinInvoice::STATUS_SENT,
        };
        $invoice->save();

        return $invoice;
    }

    /**
     * @return list<array{account_id: int, debit?: int, credit?: int, memo?: string|null, contact_id?: int|null, fund_id?: int|null}>
     */
    private function journalLines(FinInvoice $invoice): array
    {
        $tenantId = $invoice->tenant_id;
        $receivable = $this->accounts->bySystemKey($tenantId, ChartOfAccounts::RECEIVABLE);
        $defaultIncome = $this->accounts->bySystemKey($tenantId, ChartOfAccounts::DEFAULT_INCOME);

        $lines = [[
            'account_id' => $receivable->id,
            'debit' => $invoice->total,
            'memo' => $invoice->number,
            'contact_id' => $invoice->contact_id,
        ]];

        $income = [];
        foreach ($invoice->lines as $line) {
            $accountId = $line->account_id ?? $defaultIncome->id;
            $key = $accountId.'|'.($line->fund_id ?? '');
            $income[$key] ??= ['account_id' => $accountId, 'credit' => 0, 'fund_id' => $line->fund_id, 'memo' => $invoice->number];
            $income[$key]['credit'] += $line->line_total;
        }

        foreach ($income as $row) {
            if ($row['credit'] > 0) {
                $lines[] = $row;
            }
        }

        if ($invoice->tax_total > 0) {
            $lines[] = [
                'account_id' => $this->accounts->bySystemKey($tenantId, ChartOfAccounts::TAX_PAYABLE)->id,
                'credit' => $invoice->tax_total,
                'memo' => $invoice->number,
            ];
        }

        return $lines;
    }

    private function taxFor(string $tenantId, bool $taxEnabled, ?int $taxId, int $lineTotal): int
    {
        if (! $taxEnabled || $taxId === null) {
            return 0;
        }

        $tax = FinTax::query()->where('tenant_id', $tenantId)->where('is_active', true)->find($taxId);

        return $tax instanceof FinTax ? (int) round($lineTotal * $tax->rate_basis_points / 10000) : 0;
    }
}
