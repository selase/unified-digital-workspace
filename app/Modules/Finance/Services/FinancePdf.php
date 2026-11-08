<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\FinInvoice;
use Barryvdh\DomPDF\Facade\Pdf;

final class FinancePdf
{
    public function __construct(private readonly FinanceSettings $settings) {}

    public function output(FinInvoice $document): string
    {
        $document->loadMissing(['contact', 'lines']);

        return Pdf::loadView('finance::pdf.invoice', [
            'document' => $document,
            'settings' => $this->settings->for($document->tenant_id),
        ])->setPaper('a4')->output();
    }

    public function filename(FinInvoice $document): string
    {
        $label = match ($document->type) {
            FinInvoice::TYPE_QUOTE => 'Quote',
            FinInvoice::TYPE_CREDIT_NOTE => 'CreditNote',
            default => 'Invoice',
        };

        return $label.'-'.($document->number ?? 'draft').'.pdf';
    }
}
