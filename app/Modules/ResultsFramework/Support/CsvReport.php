<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

final class CsvReport
{
    /** @param list<list<string|int|float|null>> $rows */
    public static function download(array $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'wb');
            foreach ($rows as $row) {
                fputcsv($output, array_map(self::safeCell(...), $row), ',', '"', '');
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private static function safeCell(string|int|float|null $cell): string
    {
        if (is_string($cell) && preg_match('/\A(?:[\s\p{Z}\p{Cf}\x00-\x1f]*[=+@-]|[\t\r\n])/u', $cell) === 1) {
            return "'".$cell;
        }

        return (string) $cell;
    }
}
