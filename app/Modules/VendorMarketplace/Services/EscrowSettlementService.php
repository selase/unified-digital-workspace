<?php

declare(strict_types=1);

namespace App\Modules\VendorMarketplace\Services;

use App\Modules\VendorMarketplace\Models\EscrowAccount;
use App\Modules\VendorMarketplace\Models\EscrowDispute;
use App\Modules\VendorMarketplace\Models\EscrowLedgerEntry;
use App\Modules\VendorMarketplace\Models\ProformaInvoice;
use App\Modules\VendorMarketplace\Models\ServiceJob;
use DomainException;
use Illuminate\Support\Str;

final class EscrowSettlementService
{
    public function fundFromProforma(
        ProformaInvoice $proforma,
        string $actorUuid,
        string $idempotencyKey,
    ): EscrowAccount {
        $existingEntry = EscrowLedgerEntry::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingEntry instanceof EscrowLedgerEntry) {
            /** @var EscrowAccount $existingEscrow */
            $existingEscrow = EscrowAccount::query()->findOrFail($existingEntry->escrow_account_id);

            return $existingEscrow;
        }

        $escrow = EscrowAccount::query()
            ->where('proforma_invoice_id', $proforma->id)
            ->lockForUpdate()
            ->first();

        if (! $escrow instanceof EscrowAccount) {
            $escrow = EscrowAccount::create([
                'escrow_number' => 'ESC-'.mb_strtoupper(Str::random(8)),
                'proforma_invoice_id' => $proforma->id,
                'service_job_id' => $proforma->service_job_id,
                'vendor_profile_id' => $proforma->vendor_profile_id,
                'buyer_id' => $proforma->client_id,
                'currency' => $proforma->currency,
                'funded_amount_pesewas' => 0,
                'held_balance_pesewas' => 0,
                'status' => EscrowAccount::STATUS_PENDING_FUNDING,
            ]);
        }

        if ($escrow->status !== EscrowAccount::STATUS_PENDING_FUNDING) {
            throw new DomainException('Escrow account has already been funded or closed.');
        }

        $gross = (int) $proforma->gross_amount_pesewas;

        EscrowLedgerEntry::create([
            'escrow_account_id' => $escrow->id,
            'idempotency_key' => $idempotencyKey,
            'entry_type' => 'funding_credit',
            'direction' => 'credit',
            'beneficiary_type' => 'escrow_pool',
            'amount_pesewas' => $gross,
            'balance_after_pesewas' => $gross,
            'actor_id' => $actorUuid,
            'notes' => "Escrow funded for Proforma {$proforma->invoice_number}",
            'occurred_at' => now(),
        ]);

        $escrow->update([
            'funded_amount_pesewas' => $gross,
            'held_balance_pesewas' => $gross,
            'status' => EscrowAccount::STATUS_FUNDED,
            'funded_at' => now(),
        ]);

        $proforma->update(['status' => ProformaInvoice::STATUS_FUNDED]);
        $proforma->serviceJob()->update(['status' => ServiceJob::STATUS_IN_ESCROW]);

        return $escrow->fresh() ?? $escrow;
    }

    public function releaseEscrow(
        EscrowAccount $escrow,
        string $actorUuid,
        string $idempotencyKey,
        bool $holdPayoutForDisbursement = false,
    ): EscrowAccount {
        $vendorKey = "{$idempotencyKey}:vendor";

        $existingEntry = EscrowLedgerEntry::query()
            ->where('idempotency_key', $vendorKey)
            ->first();

        if ($existingEntry instanceof EscrowLedgerEntry) {
            return $escrow->fresh() ?? $escrow;
        }

        /** @var EscrowAccount $locked */
        $locked = EscrowAccount::query()
            ->whereKey($escrow->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($locked->status === EscrowAccount::STATUS_FROZEN_DISPUTED) {
            throw new DomainException('Cannot release escrow funds while an active dispute freeze is in effect.');
        }

        if ($locked->status !== EscrowAccount::STATUS_FUNDED || $locked->held_balance_pesewas <= 0) {
            throw new DomainException('Escrow account is not in a releasable funded state.');
        }

        /** @var ProformaInvoice $proforma */
        $proforma = $locked->proformaInvoice()->firstOrFail();

        $vendorAmount = (int) $proforma->net_vendor_payout_pesewas;
        $tenantFee = (int) $proforma->tenant_commission_pesewas;
        $platformFee = (int) $proforma->platform_commission_pesewas;

        if (($vendorAmount + $tenantFee + $platformFee) !== (int) $locked->held_balance_pesewas) {
            throw new DomainException('Escrow 3-way split sum does not equal held escrow balance.');
        }

        $runningBalance = (int) $locked->held_balance_pesewas;

        // 1. Vendor Payout
        $runningBalance -= $vendorAmount;
        EscrowLedgerEntry::create([
            'escrow_account_id' => $locked->id,
            'idempotency_key' => $vendorKey,
            'entry_type' => 'vendor_payout_debit',
            'direction' => 'debit',
            'beneficiary_type' => 'vendor',
            'amount_pesewas' => $vendorAmount,
            'balance_after_pesewas' => $runningBalance,
            'actor_id' => $actorUuid,
            'notes' => "Net vendor payout released for {$locked->escrow_number}",
            'occurred_at' => now(),
        ]);

        // 2. Tenant / Real Estate / Facility Owner Commission
        if ($tenantFee > 0) {
            $runningBalance -= $tenantFee;
            EscrowLedgerEntry::create([
                'escrow_account_id' => $locked->id,
                'idempotency_key' => "{$idempotencyKey}:tenant_commission",
                'entry_type' => 'tenant_commission_debit',
                'direction' => 'debit',
                'beneficiary_type' => 'tenant_owner',
                'amount_pesewas' => $tenantFee,
                'balance_after_pesewas' => $runningBalance,
                'actor_id' => $actorUuid,
                'notes' => "Tenant owner commission ({$proforma->tenant_commission_bps} bps) released",
                'occurred_at' => now(),
            ]);
        }

        // 3. Platform Owner Commission
        if ($platformFee > 0) {
            $runningBalance -= $platformFee;
            EscrowLedgerEntry::create([
                'escrow_account_id' => $locked->id,
                'idempotency_key' => "{$idempotencyKey}:platform_commission",
                'entry_type' => 'platform_commission_debit',
                'direction' => 'debit',
                'beneficiary_type' => 'platform_owner',
                'amount_pesewas' => $platformFee,
                'balance_after_pesewas' => $runningBalance,
                'actor_id' => $actorUuid,
                'notes' => "Platform owner commission ({$proforma->platform_commission_bps} bps) released",
                'occurred_at' => now(),
            ]);
        }

        $newStatus = $holdPayoutForDisbursement
            ? EscrowAccount::STATUS_PENDING_PAYOUT
            : EscrowAccount::STATUS_RELEASED;

        $locked->update([
            'held_balance_pesewas' => 0,
            'released_vendor_pesewas' => $vendorAmount,
            'released_tenant_commission_pesewas' => $tenantFee,
            'released_platform_commission_pesewas' => $platformFee,
            'status' => $newStatus,
            'settled_at' => now(),
        ]);

        $locked->serviceJob()->update(['status' => ServiceJob::STATUS_COMPLETED]);

        return $locked->fresh() ?? $locked;
    }

    /**
     * Atomically executes vendor payout disbursement for an escrow held in pending_payout.
     */
    public function settlePayout(
        EscrowAccount $escrow,
        string $actorUuid,
        string $payoutReference
    ): EscrowAccount {
        /** @var EscrowAccount $locked */
        $locked = EscrowAccount::query()->where('id', $escrow->id)->lockForUpdate()->firstOrFail();

        if ($locked->status === EscrowAccount::STATUS_RELEASED) {
            return $locked;
        }

        if ($locked->status !== EscrowAccount::STATUS_PENDING_PAYOUT) {
            throw new DomainException('Only escrows in pending payout state can be settled for disbursement.');
        }

        EscrowLedgerEntry::create([
            'escrow_account_id' => $locked->id,
            'idempotency_key' => "payout:{$locked->uuid}:{$payoutReference}",
            'entry_type' => 'vendor_payout_disbursed',
            'direction' => 'debit',
            'beneficiary_type' => 'vendor',
            'amount_pesewas' => (int) $locked->released_vendor_pesewas,
            'balance_after_pesewas' => 0,
            'actor_id' => $actorUuid,
            'notes' => "Vendor payout disbursed via reference {$payoutReference}",
            'occurred_at' => now(),
        ]);

        $locked->update([
            'status' => EscrowAccount::STATUS_RELEASED,
            'settled_at' => now(),
        ]);

        return $locked->fresh() ?? $locked;
    }

    public function openDispute(
        EscrowAccount $escrow,
        string $raisedByUuid,
        string $reason,
    ): EscrowDispute {
        /** @var EscrowAccount $locked */
        $locked = EscrowAccount::query()
            ->whereKey($escrow->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($locked->status !== EscrowAccount::STATUS_FUNDED) {
            throw new DomainException('Only a funded escrow account can be frozen for dispute.');
        }

        $locked->update([
            'status' => EscrowAccount::STATUS_FROZEN_DISPUTED,
        ]);

        $locked->serviceJob()->update([
            'status' => ServiceJob::STATUS_DISPUTED,
        ]);

        return EscrowDispute::create([
            'escrow_account_id' => $locked->id,
            'raised_by_id' => $raisedByUuid,
            'reason' => $reason,
            'status' => EscrowDispute::STATUS_OPEN,
        ]);
    }

    public function resolveDispute(
        EscrowDispute $dispute,
        string $resolvedByUuid,
        string $resolutionType,
        string $notes,
        string $idempotencyKey,
    ): EscrowAccount {
        if ($dispute->status === EscrowDispute::STATUS_RESOLVED) {
            throw new DomainException('This escrow dispute has already been resolved.');
        }

        /** @var EscrowAccount $locked */
        $locked = EscrowAccount::query()
            ->whereKey($dispute->escrow_account_id)
            ->lockForUpdate()
            ->firstOrFail();

        $dispute->update([
            'status' => EscrowDispute::STATUS_RESOLVED,
            'resolution_type' => $resolutionType,
            'resolution_notes' => $notes,
            'resolved_by_id' => $resolvedByUuid,
            'resolved_at' => now(),
        ]);

        if ($resolutionType === EscrowDispute::RESOLUTION_RELEASE_TO_VENDOR) {
            $locked->update(['status' => EscrowAccount::STATUS_FUNDED]);

            return $this->releaseEscrow($locked, $resolvedByUuid, $idempotencyKey);
        }

        if ($resolutionType === EscrowDispute::RESOLUTION_REFUND_TO_BUYER) {
            $refundAmount = (int) $locked->held_balance_pesewas;

            EscrowLedgerEntry::create([
                'escrow_account_id' => $locked->id,
                'idempotency_key' => "{$idempotencyKey}:buyer_refund",
                'entry_type' => 'buyer_refund_debit',
                'direction' => 'debit',
                'beneficiary_type' => 'buyer',
                'amount_pesewas' => $refundAmount,
                'balance_after_pesewas' => 0,
                'actor_id' => $resolvedByUuid,
                'notes' => "Dispute resolved in buyer favor: {$notes}",
                'occurred_at' => now(),
            ]);

            $locked->update([
                'held_balance_pesewas' => 0,
                'refunded_buyer_pesewas' => $refundAmount,
                'status' => EscrowAccount::STATUS_REFUNDED,
                'settled_at' => now(),
            ]);

            $locked->serviceJob()->update(['status' => ServiceJob::STATUS_CANCELLED]);

            return $locked->fresh() ?? $locked;
        }

        throw new DomainException("Unsupported dispute resolution type: {$resolutionType}");
    }
}
