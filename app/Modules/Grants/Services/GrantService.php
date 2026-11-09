<?php

declare(strict_types=1);

namespace App\Modules\Grants\Services;

use App\Modules\Finance\Models\FinAccount;
use App\Modules\Finance\Models\FinFund;
use App\Modules\Finance\Models\FinJournal;
use App\Modules\Finance\Services\ChartOfAccounts;
use App\Modules\Finance\Services\LedgerService;
use App\Modules\Grants\Models\GrtFunder;
use App\Modules\Grants\Models\GrtGrant;
use App\Modules\Grants\Models\GrtInstalment;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Grant agreements and the money that arrives under them. Each grant gets its own restricted fund, so
 * income and spending for the grant can be reported on their own. Received instalments post to the ledger.
 */
final class GrantService
{
    public const GRANT_INCOME = '4220';

    public function __construct(
        private readonly LedgerService $ledger,
        private readonly ChartOfAccounts $accounts,
    ) {}

    /**
     * @param  array{title: string, reference?: string|null, starts_on?: string|null, ends_on?: string|null, status?: string, purpose?: string|null, notes?: string|null, fund_id?: int|null}  $data
     */
    public function create(string $tenantId, GrtFunder $funder, int $totalAmount, array $data): GrtGrant
    {
        if ($totalAmount <= 0) {
            throw new DomainException('The grant amount must be greater than zero.');
        }
        if (isset($data['starts_on'], $data['ends_on']) && $data['ends_on'] < $data['starts_on']) {
            throw new DomainException('The grant must end after it starts.');
        }

        return DB::connection('tenant')->transaction(function () use ($tenantId, $funder, $totalAmount, $data): GrtGrant {
            $fundId = $data['fund_id'] ?? null;
            if ($fundId === null) {
                $count = FinFund::query()->where('tenant_id', $tenantId)->where('code', 'like', 'GR-%')->count() + 1;
                $fundId = FinFund::query()->create([
                    'tenant_id' => $tenantId,
                    'code' => 'GR-'.mb_str_pad((string) $count, 3, '0', STR_PAD_LEFT),
                    'name' => $data['title'].' ('.$funder->name.')',
                    'is_restricted' => true,
                ])->id;
            }

            return GrtGrant::query()->create([
                'tenant_id' => $tenantId,
                'funder_id' => $funder->id,
                'fund_id' => $fundId,
                'title' => $data['title'],
                'reference' => $data['reference'] ?? null,
                'total_amount' => $totalAmount,
                'starts_on' => $data['starts_on'] ?? null,
                'ends_on' => $data['ends_on'] ?? null,
                'status' => $data['status'] ?? 'pipeline',
                'purpose' => $data['purpose'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    public function addInstalment(GrtGrant $grant, int $amount, CarbonInterface $expectedOn, ?string $userId = null): GrtInstalment
    {
        if ($amount <= 0) {
            throw new DomainException('The instalment amount must be greater than zero.');
        }

        $planned = (int) $grant->instalments()->where('status', '!=', 'void')->sum('amount');
        if ($planned + $amount > $grant->total_amount) {
            throw new DomainException('The instalments would add up to more than the grant.');
        }

        return GrtInstalment::query()->create([
            'tenant_id' => $grant->tenant_id,
            'grant_id' => $grant->id,
            'expected_on' => $expectedOn->toDateString(),
            'amount' => $amount,
            'status' => 'expected',
            'created_by' => $userId,
        ]);
    }

    public function receive(GrtInstalment $instalment, CarbonInterface $date, ?int $depositAccountId = null, ?string $reference = null, ?string $userId = null): GrtInstalment
    {
        if ($instalment->status !== 'expected') {
            throw new DomainException('Only an expected instalment can be received.');
        }

        $grant = $instalment->grant()->with('funder')->firstOrFail();
        if (! $grant->isCommitted()) {
            throw new DomainException('Mark the grant as awarded before receiving money against it.');
        }

        return DB::connection('tenant')->transaction(function () use ($instalment, $grant, $date, $depositAccountId, $reference, $userId): GrtInstalment {
            $tenantId = $instalment->tenant_id;
            $depositId = $depositAccountId ?? $this->accounts->bySystemKey($tenantId, ChartOfAccounts::BANK)->id;
            $income = FinAccount::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'code' => self::GRANT_INCOME],
                ['name' => 'Grants received', 'type' => 'income'],
            );

            $journal = $this->ledger->post(
                $tenantId,
                $date,
                [
                    ['account_id' => $depositId, 'debit' => $instalment->amount, 'memo' => $grant->title, 'fund_id' => $grant->fund_id],
                    ['account_id' => $income->id, 'credit' => $instalment->amount, 'memo' => 'Grant: '.$grant->title.' ('.$grant->funder->name.')', 'fund_id' => $grant->fund_id],
                ],
                'Grant instalment: '.$grant->title,
                'grant_instalment',
                $instalment->id,
                $userId,
            );

            $instalment->forceFill([
                'status' => 'received',
                'received_on' => $date->toDateString(),
                'reference' => $reference,
                'deposit_account_id' => $depositId,
                'journal_id' => $journal->id,
            ])->save();

            return $instalment;
        });
    }

    public function void(GrtInstalment $instalment, ?string $userId = null): GrtInstalment
    {
        if ($instalment->status === 'void') {
            throw new DomainException('This instalment is already void.');
        }

        return DB::connection('tenant')->transaction(function () use ($instalment, $userId): GrtInstalment {
            $journal = FinJournal::query()->find($instalment->journal_id);
            if ($journal instanceof FinJournal) {
                $this->ledger->reverse($journal, null, $userId);
            }
            $instalment->forceFill(['status' => 'void'])->save();

            return $instalment;
        });
    }

    /**
     * What has been promised, received and spent for a grant. Spending is every expense posted to the grant's fund
     * inside the grant's dates.
     *
     * @return array{awarded: int, received: int, expected: int, outstanding: int, spent: int, remaining: int, spent_percent: int}
     */
    public function position(GrtGrant $grant): array
    {
        $received = (int) $grant->instalments()->where('status', 'received')->sum('amount');
        $expected = (int) $grant->instalments()->where('status', 'expected')->sum('amount');

        $spent = $grant->fund_id === null ? 0 : (int) DB::connection('tenant')->table('fin_journal_lines as l')
            ->join('fin_journals as j', 'j.id', '=', 'l.journal_id')
            ->join('fin_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('j.tenant_id', $grant->tenant_id)->where('l.fund_id', $grant->fund_id)->where('a.type', 'expense')
            ->when($grant->starts_on, fn ($query) => $query->whereDate('j.date', '>=', $grant->starts_on->toDateString()))
            ->when($grant->ends_on, fn ($query) => $query->whereDate('j.date', '<=', $grant->ends_on->toDateString()))
            ->selectRaw('COALESCE(SUM(l.debit - l.credit), 0) as total')->value('total');

        return [
            'awarded' => $grant->total_amount,
            'received' => $received,
            'expected' => $expected,
            'outstanding' => max(0, $grant->total_amount - $received),
            'spent' => $spent,
            'remaining' => $grant->total_amount - $spent,
            'spent_percent' => $grant->total_amount > 0 ? min(100, intdiv($spent * 100, $grant->total_amount)) : 0,
        ];
    }
}
