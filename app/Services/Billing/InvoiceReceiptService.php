<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use DomainException;
use Illuminate\Http\Response;

final class InvoiceReceiptService
{
    public function output(Invoice $invoice): string
    {
        return $this->generate($invoice)->output();
    }

    public function download(Invoice $invoice): Response
    {
        $pdf = $this->generate($invoice);
        $filename = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) data_get($invoice->meta, 'receipt.number'));

        return $pdf->download('Receipt-'.$filename.'.pdf');
    }

    private function generate(Invoice $invoice): \Barryvdh\DomPDF\PDF
    {
        $receipt = data_get($invoice->meta, 'receipt');
        if ($invoice->status !== Invoice::STATUS_PAID || ! is_array($receipt) || empty($receipt['number'])) {
            throw new DomainException('A verified receipt is not available for this invoice.');
        }

        return Pdf::loadView('billing.payments.receipt', ['receipt' => $receipt])->setPaper('a4');
    }
}
