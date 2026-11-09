<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Support\Money;
use Carbon\CarbonInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Turns report arrays into CSV rows and streams them as a download. Amounts are plain decimals, no currency symbols. */
final class ReportCsv
{
    /**
     * @param  list<list<string|int|float|null>>  $rows
     */
    public function download(string $filename, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($cell): string => $this->safe($cell), $row), ',', '"', '');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<list<string|int|float|null>>
     */
    public function profitAndLoss(array $report, CarbonInterface $from, CarbonInterface $to, string $currency): array
    {
        $rows = [['Profit and loss', $from->toDateString().' to '.$to->toDateString()], ['Account', 'Amount ('.$currency.')'], ['Income', '']];
        foreach ($report['income'] as $row) {
            $rows[] = [$row['account']->code.' '.$row['account']->name, Money::input($row['amount'])];
        }
        $rows[] = ['Total income', Money::input($report['total_income'])];
        $rows[] = ['Expenses', ''];
        foreach ($report['expenses'] as $row) {
            $rows[] = [$row['account']->code.' '.$row['account']->name, Money::input($row['amount'])];
        }
        $rows[] = ['Total expenses', Money::input($report['total_expenses'])];
        $rows[] = ['Net profit', Money::input($report['net_profit'])];

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<list<string|int|float|null>>
     */
    public function balanceSheet(array $report, CarbonInterface $asOf, string $currency): array
    {
        $rows = [['Balance sheet', 'as at '.$asOf->toDateString()], ['Account', 'Amount ('.$currency.')']];
        foreach (['assets' => 'Assets', 'liabilities' => 'Liabilities', 'equity' => 'Equity'] as $key => $label) {
            $rows[] = [$label, ''];
            foreach ($report[$key] as $row) {
                $rows[] = [$row['account']->code.' '.$row['account']->name, Money::input($row['amount'])];
            }
            if ($key === 'equity') {
                $rows[] = ['Profit this financial year', Money::input($report['current_year_profit'])];
                $rows[] = ['Profit from earlier years', Money::input($report['prior_years_profit'])];
            }
            $rows[] = ['Total '.mb_strtolower($label), Money::input($report['total_'.$key])];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<list<string|int|float|null>>
     */
    public function cashFlow(array $report, CarbonInterface $from, CarbonInterface $to, string $currency): array
    {
        $rows = [['Cash flow', $from->toDateString().' to '.$to->toDateString()], ['', 'Amount ('.$currency.')'], ['Cash at the start', Money::input($report['opening'])]];
        foreach ($report['sections'] as $section) {
            $rows[] = [$section['label'], ''];
            foreach ($section['rows'] as $row) {
                $rows[] = [$row['account']->code.' '.$row['account']->name, Money::input($row['amount'])];
            }
            $rows[] = ['Net from '.mb_strtolower($section['label']), Money::input($section['total'])];
        }
        $rows[] = ['Net change in cash', Money::input($report['net_change'])];
        $rows[] = ['Cash at the end', Money::input($report['closing'])];

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<list<string|int|float|null>>
     */
    public function funds(array $report, CarbonInterface $from, CarbonInterface $to, string $currency): array
    {
        $rows = [['Fund report', $from->toDateString().' to '.$to->toDateString(), '', '', '', ''], ['Fund', 'Type', 'Income ('.$currency.')', 'Spent', 'Net in period', 'Balance']];
        foreach ($report['rows'] as $row) {
            $rows[] = [$row['name'], $row['fund'] === null ? '' : ($row['fund']->is_restricted ? 'Restricted' : 'Unrestricted'), Money::input($row['income']), Money::input($row['expenses']), Money::input($row['net']), Money::input($row['balance'])];
        }
        $rows[] = ['Total', '', Money::input($report['totals']['income']), Money::input($report['totals']['expenses']), Money::input($report['totals']['net']), Money::input($report['totals']['balance'])];

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<list<string|int|float|null>>
     */
    public function trialBalance(array $report, ?string $asOf, string $currency): array
    {
        $rows = [['Trial balance', $asOf ? 'as at '.$asOf : 'to date', ''], ['Account', 'Debit ('.$currency.')', 'Credit']];
        foreach ($report['rows'] as $row) {
            $rows[] = [$row['account']->code.' '.$row['account']->name, $row['debit'] ? Money::input($row['debit']) : '', $row['credit'] ? Money::input($row['credit']) : ''];
        }
        $rows[] = ['Total', Money::input($report['debit']), Money::input($report['credit'])];

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<list<string|int|float|null>>
     */
    public function aged(array $report, string $kind, string $currency): array
    {
        $isReceivable = $kind === 'receivables';
        $rows = [['Aged '.$kind], [$isReceivable ? 'Invoice' : 'Bill', $isReceivable ? 'Customer' : 'Supplier', 'Due', 'Days late', 'Balance ('.$currency.')']];
        foreach ($report['rows'] as $row) {
            $document = $isReceivable ? $row['invoice'] : $row['bill'];
            $rows[] = [$document->number, $document->contact->name, $document->due_date?->toDateString(), $row['days_late'] ?: '', Money::input($row['balance'])];
        }
        $rows[] = ['Total', '', '', '', Money::input($report['totals']['total'])];

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<list<string|int|float|null>>
     */
    public function generalLedger(array $report, string $currency): array
    {
        $rows = [['General ledger', $report['account']->code.' '.$report['account']->name, $report['from']->toDateString().' to '.$report['to']->toDateString(), '', '', ''], ['Date', 'Entry', 'Memo', 'Debit ('.$currency.')', 'Credit', 'Balance'], ['', '', 'Opening balance', '', '', Money::input($report['opening'])]];
        foreach ($report['lines'] as $line) {
            $rows[] = [$line['date']->toDateString(), $line['number'], $line['memo'], $line['debit'] ? Money::input($line['debit']) : '', $line['credit'] ? Money::input($line['credit']) : '', Money::input($line['balance'])];
        }
        $rows[] = ['', '', 'Closing balance', Money::input($report['total_debit']), Money::input($report['total_credit']), Money::input($report['closing'])];

        return $rows;
    }

    /** Stops spreadsheet programs treating a text cell as a formula. */
    private function safe(string|int|float|null $cell): string
    {
        $text = (string) $cell;

        return $text !== '' && in_array($text[0], ['=', '+', '@'], true) ? "'".$text : $text;
    }
}
