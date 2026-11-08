<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Billing\CardOnFileService;
use Illuminate\Console\Command;

final class ChargeSavedCards extends Command
{
    protected $signature = 'billing:charge-saved-cards';

    protected $description = 'Charge the saved card of each tenant whose invoice is due and unpaid';

    public function handle(CardOnFileService $cards): int
    {
        if (! $cards->enabled()) {
            $this->components->info('Saved-card charging is switched off (BILLING_CARD_AUTOCHARGE).');

            return self::SUCCESS;
        }

        $this->components->info("Invoices paid by saved card: {$cards->chargeDueInvoices()}");

        return self::SUCCESS;
    }
}
