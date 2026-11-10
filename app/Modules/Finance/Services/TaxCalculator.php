<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\FinTax;

/** Works out the tax on an amount for a tax code, honouring the organisation's tax switch. */
final class TaxCalculator
{
    /**
     * Zero-rated and exempt codes keep their tax_id even though the amount is nil, so they can be reported.
     *
     * @return array{tax_id: int|null, amount: int}
     */
    public function resolve(string $tenantId, bool $taxEnabled, ?int $taxId, int $net): array
    {
        if (! $taxEnabled || $taxId === null) {
            return ['tax_id' => null, 'amount' => 0];
        }

        $tax = FinTax::query()->where('tenant_id', $tenantId)->where('is_active', true)->find($taxId);
        if (! $tax instanceof FinTax) {
            return ['tax_id' => null, 'amount' => 0];
        }

        return ['tax_id' => $tax->id, 'amount' => (int) round($net * $tax->rate_basis_points / 10000)];
    }
}
