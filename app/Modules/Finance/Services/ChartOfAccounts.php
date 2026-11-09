<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\FinAccount;
use App\Modules\Finance\Models\FinTax;
use DomainException;

/**
 * Seeds and resolves the chart of accounts. System keys let the ledger find
 * the receivable, tax and clearing accounts even if the owner renames them.
 */
final class ChartOfAccounts
{
    public const RECEIVABLE = 'accounts_receivable';

    public const PAYABLE = 'accounts_payable';

    public const TAX_PAYABLE = 'tax_payable';

    public const BANK = 'bank';

    public const GATEWAY_CLEARING = 'gateway_clearing';

    public const DEFAULT_INCOME = 'default_income';

    public const RETAINED_EARNINGS = 'retained_earnings';

    /**
     * @var list<array{0: string, 1: string, 2: string, 3: string|null}>
     */
    private const GENERAL_BUSINESS = [
        ['1000', 'Bank', 'asset', self::BANK],
        ['1010', 'Paystack clearing', 'asset', self::GATEWAY_CLEARING],
        ['1020', 'Mobile money', 'asset', null],
        ['1100', 'Accounts receivable', 'asset', self::RECEIVABLE],
        ['1200', 'Prepaid expenses', 'asset', null],
        ['1500', 'Equipment', 'asset', null],
        ['1590', 'Accumulated depreciation', 'asset', null],
        ['2000', 'Accounts payable', 'liability', self::PAYABLE],
        ['2100', 'VAT, NHIL and GETFund payable', 'liability', self::TAX_PAYABLE],
        ['2200', 'Accrued expenses', 'liability', null],
        ['2500', 'Loans', 'liability', null],
        ['3000', "Owner's equity", 'equity', null],
        ['3900', 'Retained earnings', 'equity', self::RETAINED_EARNINGS],
        ['4000', 'Service revenue', 'income', self::DEFAULT_INCOME],
        ['4100', 'Other income', 'income', null],
        ['5000', 'Cost of services', 'expense', null],
        ['6000', 'Salaries and wages', 'expense', null],
        ['6100', 'Rent and utilities', 'expense', null],
        ['6200', 'Hosting and cloud', 'expense', null],
        ['6210', 'Software subscriptions', 'expense', null],
        ['6300', 'Marketing', 'expense', null],
        ['6400', 'Bank and gateway charges', 'expense', null],
        ['6500', 'Professional fees', 'expense', null],
        ['6900', 'Depreciation', 'expense', null],
    ];

    /**
     * @var list<array{0: string, 1: string, 2: string}>
     */
    private const NGO_ACCOUNTS = [
        ['3100', 'Net assets: unrestricted', 'equity'],
        ['3200', 'Net assets: restricted', 'equity'],
        ['4200', 'Donations: unrestricted', 'income'],
        ['4210', 'Donations: restricted', 'income'],
        ['4220', 'Grants received', 'income'],
        ['5100', 'Programme expenses', 'expense'],
        ['5200', 'Administration expenses', 'expense'],
        ['5300', 'Fundraising expenses', 'expense'],
    ];

    public function seed(string $tenantId): void
    {
        foreach (self::GENERAL_BUSINESS as [$code, $name, $type, $systemKey]) {
            FinAccount::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'code' => $code],
                ['name' => $name, 'type' => $type, 'system_key' => $systemKey],
            );
        }
    }

    /**
     * Adds the accounts a foundation or NGO needs on top of the general business chart: net assets,
     * donation and grant income, and expenses split by programme, administration and fundraising.
     * Safe to run more than once. Returns how many accounts were created.
     */
    public function addNgoAccounts(string $tenantId): int
    {
        $this->ensureSeeded($tenantId);
        $created = 0;

        foreach (self::NGO_ACCOUNTS as [$code, $name, $type]) {
            $account = FinAccount::query()->firstOrCreate(['tenant_id' => $tenantId, 'code' => $code], ['name' => $name, 'type' => $type]);
            $created += $account->wasRecentlyCreated ? 1 : 0;
        }

        return $created;
    }

    public function seedGhanaTax(string $tenantId): FinTax
    {
        return FinTax::query()->firstOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'VAT + NHIL + GETFund (20%)'],
            [
                'components' => [
                    ['name' => 'VAT', 'rate_basis_points' => 1500],
                    ['name' => 'NHIL', 'rate_basis_points' => 250],
                    ['name' => 'GETFund', 'rate_basis_points' => 250],
                ],
                'rate_basis_points' => 2000,
                'is_active' => false,
            ],
        );
    }

    public function ensureSeeded(string $tenantId): void
    {
        if (! FinAccount::query()->where('tenant_id', $tenantId)->exists()) {
            $this->seed($tenantId);
        }
    }

    public function bySystemKey(string $tenantId, string $key): FinAccount
    {
        $this->ensureSeeded($tenantId);

        $account = FinAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('system_key', $key)
            ->first();

        if (! $account instanceof FinAccount) {
            throw new DomainException("The system account [{$key}] is missing from the chart of accounts.");
        }

        return $account;
    }
}
