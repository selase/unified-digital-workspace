<?php

declare(strict_types=1);

namespace App\Modules\Finance\Contracts;

use App\Modules\Finance\Models\FinInvoice;

/**
 * How an issued tax invoice is reported to the tax authority's e-VAT system. The platform does not ship a certified
 * GRA connection: until one is configured, the null provider leaves invoices waiting so they can all be submitted
 * once the connection exists.
 */
interface EvatProvider
{
    public function isConfigured(): bool;

    /**
     * @return array{status: 'submitted'|'failed'|'pending', reference: string|null, message: string|null}
     */
    public function submit(FinInvoice $invoice): array;
}
