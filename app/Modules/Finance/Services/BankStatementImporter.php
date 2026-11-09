<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\FinBankAccount;
use App\Modules\Finance\Models\FinBankTransaction;
use App\Modules\Finance\Support\Money;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Reads a CSV bank statement. Needs a header row with a date column and either one
 * signed amount column or separate debit/withdrawal and credit/deposit columns.
 * Re-importing the same file skips rows already imported.
 */
final class BankStatementImporter
{
    private const DATE_HEADERS = ['date', 'transaction date', 'value date', 'posting date'];

    private const DESCRIPTION_HEADERS = ['description', 'details', 'narration', 'narrative', 'particulars', 'transaction details'];

    private const REFERENCE_HEADERS = ['reference', 'ref', 'transaction id', 'reference number'];

    private const AMOUNT_HEADERS = ['amount'];

    private const OUT_HEADERS = ['debit', 'withdrawal', 'withdrawals', 'money out', 'paid out'];

    private const IN_HEADERS = ['credit', 'deposit', 'deposits', 'money in', 'paid in'];

    /**
     * @return array{imported: int, skipped: int, errors: list<string>}
     */
    public function import(FinBankAccount $bank, string $path, ?string $userId = null): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new DomainException('The file could not be read.');
        }

        try {
            $header = fgetcsv($handle, 0, ',', '"', '');
            if (! is_array($header)) {
                throw new DomainException('The file is empty.');
            }

            $columns = $this->mapColumns($header);
            $imported = 0;
            $skipped = 0;
            $errors = [];
            $seen = [];
            $row = 1;

            DB::connection('tenant')->transaction(function () use ($handle, $bank, $columns, $userId, &$imported, &$skipped, &$errors, &$seen, &$row): void {
                while (($cells = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                    $row++;
                    if ($cells === [null] || implode('', array_map(fn ($cell): string => (string) $cell, $cells)) === '') {
                        continue;
                    }

                    try {
                        $line = $this->parseRow($cells, $columns);
                    } catch (DomainException $error) {
                        $errors[] = 'Row '.$row.': '.$error->getMessage();

                        continue;
                    }

                    $base = sha1($bank->id.'|'.$line['date'].'|'.$line['amount'].'|'.$line['description'].'|'.$line['reference']);
                    $seen[$base] = ($seen[$base] ?? 0) + 1;
                    $fingerprint = sha1($base.'#'.$seen[$base]);

                    $created = FinBankTransaction::query()->firstOrCreate(
                        ['bank_account_id' => $bank->id, 'fingerprint' => $fingerprint],
                        $line + ['tenant_id' => $bank->tenant_id, 'status' => FinBankTransaction::STATUS_UNMATCHED, 'created_by' => $userId],
                    );
                    $created->wasRecentlyCreated ? $imported++ : $skipped++;
                }
            });
        } finally {
            fclose($handle);
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * @param  array<int, string|null>  $header
     * @return array{date: int, description: int|null, reference: int|null, amount: int|null, out: int|null, in: int|null}
     */
    private function mapColumns(array $header): array
    {
        $names = array_map(fn ($cell): string => mb_strtolower(mb_trim((string) $cell)), $header);
        $find = function (array $options) use ($names): ?int {
            foreach ($options as $option) {
                $index = array_search($option, $names, true);
                if ($index !== false) {
                    return (int) $index;
                }
            }

            return null;
        };

        $columns = [
            'date' => $find(self::DATE_HEADERS),
            'description' => $find(self::DESCRIPTION_HEADERS),
            'reference' => $find(self::REFERENCE_HEADERS),
            'amount' => $find(self::AMOUNT_HEADERS),
            'out' => $find(self::OUT_HEADERS),
            'in' => $find(self::IN_HEADERS),
        ];

        if ($columns['date'] === null || ($columns['amount'] === null && $columns['out'] === null && $columns['in'] === null)) {
            throw new DomainException('The first row must name a Date column and an Amount column (or Debit and Credit columns).');
        }

        return $columns;
    }

    /**
     * @param  array<int, string|null>  $cells
     * @param  array{date: int, description: int|null, reference: int|null, amount: int|null, out: int|null, in: int|null}  $columns
     * @return array{date: string, description: string|null, reference: string|null, amount: int}
     */
    private function parseRow(array $cells, array $columns): array
    {
        $date = $this->parseDate((string) ($cells[$columns['date']] ?? ''));

        if ($columns['amount'] !== null) {
            $amount = $this->parseAmount((string) ($cells[$columns['amount']] ?? ''));
        } else {
            $out = $columns['out'] !== null ? $this->parseAmount((string) ($cells[$columns['out']] ?? '')) : 0;
            $in = $columns['in'] !== null ? $this->parseAmount((string) ($cells[$columns['in']] ?? '')) : 0;
            $amount = abs($in) - abs($out);
        }

        if ($amount === 0) {
            throw new DomainException('the amount is zero or missing.');
        }

        $text = fn (?int $index): ?string => $index !== null && mb_trim((string) ($cells[$index] ?? '')) !== '' ? mb_substr(mb_trim((string) $cells[$index]), 0, 255) : null;

        return ['date' => $date, 'description' => $text($columns['description']), 'reference' => $text($columns['reference']), 'amount' => $amount];
    }

    private function parseDate(string $value): string
    {
        $value = mb_trim($value);
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'j M Y', 'd M Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);
            } catch (Throwable) {
                continue;
            }
            if ($date !== null && $date->format($format) === $value) {
                return $date->toDateString();
            }
        }

        throw new DomainException('the date "'.$value.'" is not recognised (use YYYY-MM-DD or DD/MM/YYYY).');
    }

    private function parseAmount(string $value): int
    {
        $value = mb_trim($value);
        if ($value === '') {
            return 0;
        }

        $negative = str_starts_with($value, '-') || (str_starts_with($value, '(') && str_ends_with($value, ')'));
        $clean = preg_replace('/[^0-9.]/', '', $value) ?? '';
        if ($clean === '' || ! is_numeric($clean)) {
            throw new DomainException('the amount "'.$value.'" is not a number.');
        }

        $minor = Money::toMinor($clean);

        return $negative ? -$minor : $minor;
    }
}
