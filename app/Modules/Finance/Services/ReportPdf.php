<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/** Prints the same rows the CSV export uses as a tidy PDF table, headed with the organisation's name. */
final class ReportPdf
{
    public function __construct(private readonly FinanceSettings $settings) {}

    /**
     * @param  list<list<string|int|float|null>>  $rows
     */
    public function download(string $tenantId, string $name, array $rows): Response
    {
        $title = (string) ($rows[0][0] ?? 'Report');
        $subtitle = implode(' · ', array_filter(array_map(fn ($cell): string => (string) $cell, array_slice($rows[0] ?? [], 1))));
        $profile = $this->settings->for($tenantId);

        $output = Pdf::loadView('finance::pdf.report', [
            'title' => $title,
            'subtitle' => $subtitle,
            'header' => $rows[1] ?? [],
            'body' => array_slice($rows, 2),
            'organisation' => $profile->legal_name ?: (string) config('app.name'),
        ])->setPaper('a4', count($rows[1] ?? []) > 4 ? 'landscape' : 'portrait')->output();

        return response($output, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$name.'.pdf"']);
    }
}
