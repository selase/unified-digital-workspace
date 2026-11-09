<?php

declare(strict_types=1);

namespace App\Modules\Donations\Services;

use App\Modules\Donations\Models\DonDonation;
use App\Modules\Finance\Services\FinanceSettings;
use Barryvdh\DomPDF\Facade\Pdf;

final class DonationReceiptPdf
{
    public function __construct(private readonly FinanceSettings $settings) {}

    public function output(DonDonation $donation): string
    {
        $donation->loadMissing(['donor', 'campaign', 'fund']);

        return Pdf::loadView('donations::pdf.receipt', [
            'donation' => $donation,
            'settings' => $this->settings->for($donation->tenant_id),
        ])->setPaper('a4')->output();
    }

    public function filename(DonDonation $donation): string
    {
        return 'Receipt-'.$donation->receipt_number.'.pdf';
    }
}
