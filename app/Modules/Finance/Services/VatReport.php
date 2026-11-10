<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\FinTax;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The figures a VAT return is built from: sales and the output tax on them, purchases and the input tax on them,
 * and the net amount due. Credit notes count against the side they belong to. The tax recorded in the ledger over
 * the same period is shown beside the document figures so any difference is visible.
 */
final class VatReport
{
    /**
     * @return array{from: CarbonInterface, to: CarbonInterface, sales: array<string, int>, purchases: array<string, int>, output: array{total: int, components: array<string, int>}, input: array{total: int, components: array<string, int>}, net_payable: int, ledger: array{output: int, input: int}, reconciled: bool}
     */
    public function summary(string $tenantId, CarbonInterface $from, CarbonInterface $to): array
    {
        $taxes = FinTax::query()->where('tenant_id', $tenantId)->get()->keyBy('id');
        $start = $from->toDateString();
        $end = $to->toDateString();

        $salesRows = DB::connection('tenant')->table('fin_invoice_lines as l')
            ->join('fin_invoices as i', 'i.id', '=', 'l.invoice_id')
            ->where('i.tenant_id', $tenantId)->whereIn('i.type', ['invoice', 'credit_note'])->whereNotIn('i.status', ['draft', 'void'])
            ->whereDate('i.issue_date', '>=', $start)->whereDate('i.issue_date', '<=', $end)
            ->selectRaw("l.tax_id, CASE WHEN i.type = 'credit_note' THEN -1 ELSE 1 END as sign, l.line_total as net, l.tax_amount as tax")->get();

        $billRows = DB::connection('tenant')->table('fin_bill_lines as l')
            ->join('fin_bills as b', 'b.id', '=', 'l.bill_id')
            ->where('b.tenant_id', $tenantId)->whereNotIn('b.status', ['draft', 'void'])
            ->whereDate('b.issue_date', '>=', $start)->whereDate('b.issue_date', '<=', $end)
            ->selectRaw("l.tax_id, CASE WHEN b.type = 'credit' THEN -1 ELSE 1 END as sign, l.amount as net, l.tax_amount as tax")->get();

        $expenseRows = DB::connection('tenant')->table('fin_expenses')
            ->where('tenant_id', $tenantId)->whereNull('voided_at')
            ->whereDate('date', '>=', $start)->whereDate('date', '<=', $end)
            ->selectRaw('tax_id, 1 as sign, amount - tax_amount as net, tax_amount as tax')->get();

        [$sales, $output] = $this->fold($salesRows, $taxes);
        [$purchases, $input] = $this->fold($billRows->concat($expenseRows), $taxes);

        $ledger = [
            'output' => -$this->ledgerNet($tenantId, ChartOfAccounts::TAX_PAYABLE, $start, $end),
            'input' => $this->ledgerNet($tenantId, ChartOfAccounts::INPUT_TAX, $start, $end),
        ];

        return [
            'from' => $from,
            'to' => $to,
            'sales' => $sales,
            'purchases' => $purchases,
            'output' => $output,
            'input' => $input,
            'net_payable' => $output['total'] - $input['total'],
            'ledger' => $ledger,
            'reconciled' => $ledger['output'] === $output['total'] && $ledger['input'] === $input['total'],
        ];
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  Collection<int, FinTax>  $taxes
     * @return array{0: array<string, int>, 1: array{total: int, components: array<string, int>}}
     */
    private function fold(Collection $rows, Collection $taxes): array
    {
        $net = ['standard' => 0, 'zero_rated' => 0, 'exempt' => 0, 'uncoded' => 0];
        $taxByCode = [];

        foreach ($rows as $row) {
            $sign = (int) $row->sign;
            $code = $row->tax_id !== null ? $taxes->get((int) $row->tax_id) : null;
            $category = $code instanceof FinTax ? $code->category : 'uncoded';
            $net[$category] = ($net[$category] ?? 0) + $sign * (int) $row->net;

            if ((int) $row->tax !== 0 && $code instanceof FinTax) {
                $taxByCode[$code->id] = ($taxByCode[$code->id] ?? 0) + $sign * (int) $row->tax;
            }
        }

        $components = [];
        $total = 0;
        foreach ($taxByCode as $taxId => $amount) {
            $total += $amount;
            $code = $taxes->get($taxId);
            $parts = collect((array) $code->components)->filter(fn ($part): bool => (int) ($part['rate_basis_points'] ?? 0) > 0)->values();
            $rate = (int) $parts->sum('rate_basis_points');

            if ($parts->isEmpty() || $rate === 0) {
                $components[$code->name] = ($components[$code->name] ?? 0) + $amount;

                continue;
            }

            $allocated = 0;
            foreach ($parts as $index => $part) {
                $share = $index === $parts->count() - 1 ? $amount - $allocated : (int) round($amount * (int) $part['rate_basis_points'] / $rate);
                $allocated += $share;
                $components[(string) $part['name']] = ($components[(string) $part['name']] ?? 0) + $share;
            }
        }

        return [$net, ['total' => $total, 'components' => $components]];
    }

    private function ledgerNet(string $tenantId, string $systemKey, string $start, string $end): int
    {
        return (int) DB::connection('tenant')->table('fin_journal_lines as l')
            ->join('fin_journals as j', 'j.id', '=', 'l.journal_id')
            ->join('fin_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('j.tenant_id', $tenantId)->where('a.system_key', $systemKey)
            ->whereDate('j.date', '>=', $start)->whereDate('j.date', '<=', $end)
            ->selectRaw('COALESCE(SUM(l.debit - l.credit), 0) as net')->value('net');
    }
}
