<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Models\Tenant;
use App\Modules\Finance\Models\FinContact;
use App\Modules\Finance\Models\FinInvoice;
use App\Modules\Finance\Models\FinJournal;
use App\Modules\Finance\Models\FinPayment;
use App\Modules\Finance\Models\FinPaymentAllocation;
use App\Modules\Finance\Support\Money;
use App\Services\Alerts\AlertDispatcher;
use App\Services\Alerts\AlertEvent;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

final class PaymentService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly NumberSequence $numbers,
        private readonly FinanceSettings $settings,
        private readonly ChartOfAccounts $accounts,
        private readonly InvoiceService $invoices,
    ) {}

    /**
     * Records money received and spreads it over invoices. Debit the deposit account,
     * credit receivables. Anything not allocated stays as customer credit.
     *
     * @param  array<int, int>  $allocations  invoice id => amount
     * @param  array{method?: string, reference?: string|null, gateway?: string|null, gateway_reference?: string|null, deposit_account_id?: int|null, notes?: string|null}  $details
     */
    public function record(string $tenantId, FinContact $contact, int $amount, CarbonInterface $date, array $allocations, array $details = [], ?string $userId = null): FinPayment
    {
        if ($amount <= 0) {
            throw new DomainException('The payment amount must be greater than zero.');
        }

        if (array_sum($allocations) > $amount) {
            throw new DomainException('Allocations are larger than the payment.');
        }

        $gateway = $details['gateway'] ?? null;
        $gatewayReference = $details['gateway_reference'] ?? null;

        if ($gateway !== null && $gatewayReference !== null) {
            $existing = FinPayment::query()
                ->where('tenant_id', $tenantId)
                ->where('gateway', $gateway)
                ->where('gateway_reference', $gatewayReference)
                ->first();

            if ($existing instanceof FinPayment) {
                return $existing;
            }
        }

        $payment = DB::connection('tenant')->transaction(function () use ($tenantId, $contact, $amount, $date, $allocations, $details, $userId, $gateway): FinPayment {
            $settings = $this->settings->for($tenantId);

            $targets = FinInvoice::query()
                ->where('tenant_id', $tenantId)
                ->where('contact_id', $contact->id)
                ->whereIn('id', array_keys($allocations))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($allocations as $invoiceId => $allocated) {
                $target = $targets->get($invoiceId);
                if (! $target instanceof FinInvoice || ! in_array($target->status, [FinInvoice::STATUS_SENT, FinInvoice::STATUS_PARTLY_PAID], true)) {
                    throw new DomainException('A payment can only be applied to this customer\'s open invoices.');
                }
                if ($allocated <= 0 || $allocated > $target->balanceDue()) {
                    throw new DomainException('An allocation is larger than the invoice balance.');
                }
            }

            $depositKey = $gateway !== null ? ChartOfAccounts::GATEWAY_CLEARING : ChartOfAccounts::BANK;
            $depositAccountId = $details['deposit_account_id'] ?? $this->accounts->bySystemKey($tenantId, $depositKey)->id;

            $payment = FinPayment::query()->create([
                'tenant_id' => $tenantId,
                'number' => $this->numbers->next($tenantId, 'payment', $this->settings->prefix($settings, 'payment')),
                'contact_id' => $contact->id,
                'date' => $date->toDateString(),
                'amount' => $amount,
                'method' => $details['method'] ?? 'bank_transfer',
                'reference' => $details['reference'] ?? null,
                'gateway' => $gateway,
                'gateway_reference' => $details['gateway_reference'] ?? null,
                'deposit_account_id' => $depositAccountId,
                'notes' => $details['notes'] ?? null,
                'created_by' => $userId,
            ]);

            $journal = $this->ledger->post(
                $tenantId,
                $date,
                [
                    ['account_id' => $depositAccountId, 'debit' => $amount, 'memo' => $payment->number, 'contact_id' => $contact->id],
                    ['account_id' => $this->accounts->bySystemKey($tenantId, ChartOfAccounts::RECEIVABLE)->id, 'credit' => $amount, 'memo' => $payment->number, 'contact_id' => $contact->id],
                ],
                'Payment '.$payment->number,
                'payment',
                $payment->id,
                $userId,
            );
            $payment->forceFill(['journal_id' => $journal->id])->save();

            foreach ($allocations as $invoiceId => $allocated) {
                FinPaymentAllocation::query()->create(['payment_id' => $payment->id, 'invoice_id' => $invoiceId, 'amount' => $allocated]);
                $this->invoices->refreshPaymentStatus($targets->get($invoiceId));
            }

            return $payment;
        });

        $this->alertPaymentReceived($tenantId, $contact, $payment);

        return $payment;
    }

    /** Reverses the payment's journal and re-opens the invoices it settled. */
    public function void(FinPayment $payment, ?string $userId = null): void
    {
        DB::connection('tenant')->transaction(function () use ($payment, $userId): void {
            $journal = FinJournal::query()->find($payment->journal_id);
            if ($journal instanceof FinJournal) {
                $this->ledger->reverse($journal, null, $userId);
            }

            $invoiceIds = $payment->allocations()->pluck('invoice_id');
            $payment->allocations()->delete();

            FinInvoice::query()->whereIn('id', $invoiceIds)->get()->each(
                fn (FinInvoice $invoice): FinInvoice => $this->invoices->refreshPaymentStatus($invoice)
            );

            $payment->delete();
        });
    }

    private function alertPaymentReceived(string $tenantId, FinContact $contact, FinPayment $payment): void
    {
        $tenant = Tenant::query()->find($tenantId);
        if (! $tenant instanceof Tenant) {
            return;
        }

        app(AlertDispatcher::class)->dispatchSafely(
            $tenant,
            AlertEvent::FINANCE_PAYMENT,
            'Payment received',
            $contact->name.' paid '.Money::format($payment->amount).' ('.$payment->number.').',
            'finance-payment-'.$payment->id,
            route('finance.payments.index', [], false),
        );
    }
}
