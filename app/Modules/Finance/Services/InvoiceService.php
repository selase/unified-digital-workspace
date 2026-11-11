<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\FinContact;
use App\Modules\Finance\Models\FinInvoice;
use App\Modules\Finance\Models\FinInvoiceLine;
use DomainException;
use Illuminate\Support\Facades\DB;

final class InvoiceService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly NumberSequence $numbers,
        private readonly FinanceSettings $settings,
        private readonly ChartOfAccounts $accounts,
        private readonly TaxCalculator $taxes,
        private readonly StockService $stock,
    ) {}

    /**
     * @param  array{type?: string, issue_date: string, due_date?: string|null, notes?: string|null, terms?: string|null, recurring_id?: int|null, currency?: string|null, exchange_rate?: float|string|null}  $data
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
        [$currency, $rate] = $this->currencyAndRate($settings->base_currency, $data, $invoice);

        return DB::connection('tenant')->transaction(function () use ($tenantId, $contact, $data, $lines, $invoice, $userId, $settings, $currency, $rate): FinInvoice {
            $invoice ??= new FinInvoice(['tenant_id' => $tenantId, 'status' => FinInvoice::STATUS_DRAFT, 'created_by' => $userId]);

            $invoice->fill([
                'type' => $data['type'] ?? ($invoice->exists ? $invoice->type : FinInvoice::TYPE_INVOICE),
                'contact_id' => $contact->id,
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'] ?? null,
                'currency' => $currency,
                'exchange_rate' => $rate,
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
                $tax = $currency === $settings->base_currency
                    ? $this->taxes->resolve($tenantId, $settings->tax_enabled, isset($line['tax_id']) ? (int) $line['tax_id'] : null, $lineTotal)
                    : ['tax_id' => null, 'amount' => 0];
                $taxAmount = $tax['amount'];

                FinInvoiceLine::query()->create([
                    'invoice_id' => $invoice->id,
                    'position' => $position,
                    'item_id' => $line['item_id'] ?? null,
                    'description' => $line['description'],
                    'quantity_thousandths' => $quantity,
                    'unit_price' => (int) $line['unit_price'],
                    'account_id' => $line['account_id'] ?? null,
                    'tax_id' => $tax['tax_id'],
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
            $key = match ($invoice->type) {
                FinInvoice::TYPE_QUOTE => 'quote',
                FinInvoice::TYPE_CREDIT_NOTE => 'credit_note',
                default => 'invoice',
            };
            $invoice->number = $this->numbers->next($tenantId, $key, $this->settings->prefix($settings, $key));
            $terms = $invoice->contact->payment_terms_days ?? $settings->default_payment_terms_days;
            $invoice->due_date ??= $invoice->issue_date->copy()->addDays($terms);
            $invoice->status = FinInvoice::STATUS_SENT;
            $invoice->sent_at = now();

            if (! $isQuote) {
                $isCredit = $invoice->type === FinInvoice::TYPE_CREDIT_NOTE;
                $original = $isCredit ? $this->originalFor($invoice) : null;
                if ($original instanceof FinInvoice && $invoice->total > $original->balanceDue()) {
                    throw new DomainException('A credit note cannot be more than the amount still owed on '.$original->number.'.');
                }

                $journal = $this->ledger->post(
                    $tenantId,
                    $invoice->issue_date,
                    $this->journalLines($invoice, $isCredit),
                    ($isCredit ? 'Credit note ' : 'Invoice ').$invoice->number,
                    $isCredit ? 'credit_note' : 'invoice',
                    $invoice->id,
                    $userId,
                );
                $invoice->journal_id = $journal->id;

                $this->stock->applyDocument($invoice, $userId);

                if ($original instanceof FinInvoice) {
                    $invoice->status = FinInvoice::STATUS_APPLIED;
                    $original->increment('credits_applied', $invoice->total);
                    $this->refreshPaymentStatus($original->refresh());
                }
            }

            $invoice->save();
            app(EvatQueue::class)->markIssued($invoice, (bool) $settings->evat_enabled);

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
                    'currency' => $quote->currency,
                    'exchange_rate' => $quote->exchange_rate,
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

        if ($invoice->type === FinInvoice::TYPE_INVOICE && $invoice->credits_applied > 0) {
            throw new DomainException('This invoice has credit notes applied. Void them first.');
        }

        return DB::connection('tenant')->transaction(function () use ($invoice, $userId): FinInvoice {
            if ($invoice->journal_id !== null) {
                $this->ledger->reverse($invoice->journal()->firstOrFail(), null, $userId);
            }

            if ($invoice->type === FinInvoice::TYPE_CREDIT_NOTE && $invoice->status === FinInvoice::STATUS_APPLIED) {
                $original = $this->originalFor($invoice);
                if ($original instanceof FinInvoice) {
                    $original->decrement('credits_applied', $invoice->total);
                    $this->refreshPaymentStatus($original->refresh());
                }
            }

            $this->stock->reverseDocument($invoice, $userId);
            $invoice->forceFill(['status' => FinInvoice::STATUS_VOID, 'voided_at' => now()])->save();

            return $invoice;
        });
    }

    /** Starts a draft credit note that copies an open invoice's lines; edit it to credit only part. */
    public function createCreditNote(FinInvoice $invoice, ?string $userId = null): FinInvoice
    {
        if ($invoice->type !== FinInvoice::TYPE_INVOICE || ! in_array($invoice->status, [FinInvoice::STATUS_SENT, FinInvoice::STATUS_PARTLY_PAID], true) || $invoice->balanceDue() <= 0) {
            throw new DomainException('A credit note can only be raised against an issued invoice that still has a balance.');
        }

        $invoice->load(['contact', 'lines']);

        return DB::connection('tenant')->transaction(function () use ($invoice, $userId): FinInvoice {
            $note = $this->saveDraft(
                $invoice->tenant_id,
                $invoice->contact,
                ['type' => FinInvoice::TYPE_CREDIT_NOTE, 'issue_date' => today()->toDateString(), 'notes' => 'Credit for '.$invoice->number, 'currency' => $invoice->currency, 'exchange_rate' => $invoice->exchange_rate],
                $invoice->lines->map(fn (FinInvoiceLine $line): array => [
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
            $note->forceFill(['credit_for_id' => $invoice->id])->save();

            return $note;
        });
    }

    public function refreshPaymentStatus(FinInvoice $invoice): FinInvoice
    {
        if (in_array($invoice->status, [FinInvoice::STATUS_VOID, FinInvoice::STATUS_DRAFT], true)) {
            return $invoice;
        }

        $paid = (int) $invoice->allocations()->sum('amount');
        $covered = $paid + $invoice->credits_applied;
        $invoice->amount_paid = $paid;
        $invoice->status = match (true) {
            $covered >= $invoice->total => FinInvoice::STATUS_PAID,
            $covered > 0 => FinInvoice::STATUS_PARTLY_PAID,
            default => FinInvoice::STATUS_SENT,
        };
        $invoice->save();

        return $invoice;
    }

    /**
     * @return list<array{account_id: int, debit?: int, credit?: int, memo?: string|null, contact_id?: int|null, fund_id?: int|null}>
     */
    /**
     * A document is in the base currency unless a foreign one is asked for, in which case a rate to the base currency is required.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: string, 1: float}
     */
    private function currencyAndRate(string $base, array $data, ?FinInvoice $existing): array
    {
        $currency = mb_strtoupper((string) ($data['currency'] ?? ($existing?->currency ?? $base)));
        if ($currency === $base) {
            return [$base, 1.0];
        }
        if (! array_key_exists($currency, FinInvoice::FOREIGN_CURRENCIES)) {
            throw new DomainException('That currency is not supported.');
        }
        $rate = (float) ($data['exchange_rate'] ?? ($existing?->exchange_rate ?? 0));
        if ($rate <= 0) {
            throw new DomainException('Enter the exchange rate: how many '.$base.' one '.$currency.' is worth.');
        }

        return [$currency, round($rate, 6)];
    }

    private function journalLines(FinInvoice $invoice, bool $isCredit = false): array
    {
        $tenantId = $invoice->tenant_id;
        $receivable = $this->accounts->bySystemKey($tenantId, ChartOfAccounts::RECEIVABLE);
        $defaultIncome = $this->accounts->bySystemKey($tenantId, ChartOfAccounts::DEFAULT_INCOME);

        $lines = [[
            'account_id' => $receivable->id,
            'debit' => $invoice->baseTotal(),
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

        $income = array_values(array_filter($income, fn (array $row): bool => $row['credit'] > 0));
        foreach ($income as $index => $row) {
            $income[$index]['credit'] = $invoice->toBase($row['credit']);
        }
        if ($income !== [] && $invoice->tax_total === 0) {
            // Converting each line can differ from converting the total by a few minor units; the biggest line absorbs it.
            $difference = $invoice->baseTotal() - array_sum(array_column($income, 'credit'));
            $biggest = array_search(max(array_column($income, 'credit')), array_column($income, 'credit'), true);
            $income[$biggest]['credit'] += $difference;
        }
        foreach ($income as $row) {
            $lines[] = $row;
        }

        if ($invoice->tax_total > 0) {
            $lines[] = [
                'account_id' => $this->accounts->bySystemKey($tenantId, ChartOfAccounts::TAX_PAYABLE)->id,
                'credit' => $invoice->tax_total,
                'memo' => $invoice->number,
            ];
        }

        if ($isCredit) {
            // A credit note is the invoice posted the other way round.
            $lines = array_map(function (array $line): array {
                $flipped = $line;
                unset($flipped['debit'], $flipped['credit']);
                if (isset($line['debit'])) {
                    $flipped['credit'] = $line['debit'];
                }
                if (isset($line['credit'])) {
                    $flipped['debit'] = $line['credit'];
                }

                return $flipped;
            }, $lines);
        }

        return $lines;
    }

    private function originalFor(FinInvoice $note): ?FinInvoice
    {
        return $note->credit_for_id === null ? null : FinInvoice::query()
            ->where('tenant_id', $note->tenant_id)
            ->lockForUpdate()
            ->find($note->credit_for_id);
    }
}
