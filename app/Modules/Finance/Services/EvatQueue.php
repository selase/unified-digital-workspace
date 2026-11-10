<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Contracts\EvatProvider;
use App\Modules\Finance\Models\FinInvoice;
use Throwable;

/** Marks issued tax invoices for e-VAT and submits them through the configured provider. */
final class EvatQueue
{
    public function __construct(private readonly EvatProvider $provider) {}

    /** Called when an invoice is issued. Does nothing unless the organisation has switched e-VAT preparation on. */
    public function markIssued(FinInvoice $invoice, bool $enabled): void
    {
        if (! $enabled || $invoice->type === FinInvoice::TYPE_QUOTE || $invoice->tax_total <= 0) {
            return;
        }

        $invoice->forceFill(['evat_status' => 'pending'])->save();
        $this->submit($invoice);
    }

    public function submit(FinInvoice $invoice): FinInvoice
    {
        if ($invoice->evat_status === 'submitted' || $invoice->evat_status === 'not_required') {
            return $invoice;
        }

        try {
            $result = $this->provider->submit($invoice);
        } catch (Throwable $error) {
            report($error);
            $result = ['status' => 'failed', 'reference' => null, 'message' => 'The e-VAT service could not be reached.'];
        }

        $invoice->forceFill([
            'evat_status' => $result['status'],
            'evat_reference' => $result['reference'],
            'evat_message' => $result['message'],
            'evat_submitted_at' => $result['status'] === 'submitted' ? now() : null,
        ])->save();

        return $invoice;
    }

    /** Tries every invoice still waiting. Returns how many were accepted. */
    public function retryPending(string $tenantId): int
    {
        $accepted = 0;
        FinInvoice::query()->where('tenant_id', $tenantId)->whereIn('evat_status', ['pending', 'failed'])->orderBy('id')->each(function (FinInvoice $invoice) use (&$accepted): void {
            $this->submit($invoice);
            $accepted += $invoice->evat_status === 'submitted' ? 1 : 0;
        });

        return $accepted;
    }
}
