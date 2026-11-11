<?php

declare(strict_types=1);

namespace App\Modules\Donations\Console;

use App\Modules\Donations\Services\PledgeChargeService;
use Illuminate\Console\Command;

final class ChargePledges extends Command
{
    protected $signature = 'donations:charge-pledges';

    protected $description = 'Charge the saved cards of donors whose pledge instalment is due (only where they gave consent).';

    public function handle(PledgeChargeService $charges): int
    {
        [$charged, $failed] = $charges->chargeAllDue();
        $this->info("Charged {$charged} instalment(s); {$failed} failed.");

        return self::SUCCESS;
    }
}
