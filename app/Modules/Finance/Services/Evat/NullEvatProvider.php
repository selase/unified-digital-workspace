<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services\Evat;

use App\Modules\Finance\Contracts\EvatProvider;
use App\Modules\Finance\Models\FinInvoice;

/** Stands in until a certified e-VAT connection is set up. Nothing is sent anywhere. */
final class NullEvatProvider implements EvatProvider
{
    public function isConfigured(): bool
    {
        return false;
    }

    public function submit(FinInvoice $invoice): array
    {
        return ['status' => 'pending', 'reference' => null, 'message' => 'No e-VAT connection is configured yet. The invoice is waiting to be submitted.'];
    }
}
