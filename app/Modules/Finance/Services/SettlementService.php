<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Contracts\FinancePaymentGateway;
use App\Modules\Finance\Models\FinAccount;
use App\Modules\Finance\Models\FinSettlement;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Gateway payouts. Online payments sit in the gateway clearing account until the gateway pays them out; recording
 * the payout moves the gross out of clearing, puts the net into the bank and books the fees as a charge.
 */
final class SettlementService
{
    public function __construct(
        private readonly FinancePaymentGateway $gateway,
        private readonly LedgerService $ledger,
        private readonly ChartOfAccounts $accounts,
    ) {}

    /** Pulls payouts from the gateway and stores any not seen before. Returns how many are new. */
    public function import(string $tenantId): int
    {
        if (! $this->gateway->isConfigured($tenantId)) {
            throw new DomainException('Online payments are not set up for this organisation.');
        }

        $new = 0;
        foreach ($this->gateway->settlements($tenantId) as $row) {
            if ($row['status'] !== 'success' || $row['gross'] <= 0) {
                continue;
            }

            $settlement = FinSettlement::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'gateway' => $this->gateway->key(), 'external_id' => $row['id']],
                ['settled_on' => $row['settled_on'], 'gross' => $row['gross'], 'fees' => $row['fees'], 'net' => $row['net'], 'status' => 'imported'],
            );
            $new += $settlement->wasRecentlyCreated ? 1 : 0;
        }

        return $new;
    }

    public function post(FinSettlement $settlement, ?int $bankAccountId = null, ?string $userId = null): FinSettlement
    {
        if ($settlement->status === 'posted') {
            throw new DomainException('This payout is already in the books.');
        }
        if ($settlement->net + $settlement->fees !== $settlement->gross) {
            throw new DomainException('The payout does not add up: the net plus fees must equal the gross.');
        }

        return DB::connection('tenant')->transaction(function () use ($settlement, $bankAccountId, $userId): FinSettlement {
            $tenantId = $settlement->tenant_id;
            $bank = $bankAccountId ?? $this->accounts->bySystemKey($tenantId, ChartOfAccounts::BANK)->id;
            $clearing = $this->accounts->bySystemKey($tenantId, ChartOfAccounts::GATEWAY_CLEARING)->id;
            $charges = FinAccount::query()->firstOrCreate(['tenant_id' => $tenantId, 'code' => '6400'], ['name' => 'Bank and gateway charges', 'type' => 'expense'])->id;

            $lines = [['account_id' => $bank, 'debit' => $settlement->net, 'memo' => 'Payout '.$settlement->external_id]];
            if ($settlement->fees > 0) {
                $lines[] = ['account_id' => $charges, 'debit' => $settlement->fees, 'memo' => 'Gateway fees, payout '.$settlement->external_id];
            }
            $lines[] = ['account_id' => $clearing, 'credit' => $settlement->gross, 'memo' => 'Payout '.$settlement->external_id];

            $journal = $this->ledger->post($tenantId, CarbonImmutable::parse($settlement->settled_on->toDateString()), $lines, 'Gateway payout '.$settlement->external_id, 'settlement', $settlement->id, $userId);
            $settlement->forceFill(['status' => 'posted', 'journal_id' => $journal->id])->save();

            return $settlement;
        });
    }
}
