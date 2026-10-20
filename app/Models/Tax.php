<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class Tax extends Model
{
    use HasFactory;

    protected $connection = 'landlord';

    protected $fillable = [
        'name',
        'country_code',
        'rate',
        'is_compound',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'is_compound' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Null-country rules preserve the legacy global behavior. Country rules
     * require an explicit applicability decision based on supplier registration,
     * approved commercial rates and the supply's exemption/zero-rating status.
     * Round each payable line half-up to currency minor units before summing,
     * so invoice totals equal the displayed lines. Compound bases include the
     * preceding rounded lines; there is no separate rounding adjustment.
     *
     * @return array{taxes: list<array<string, mixed>>, total_tax: float}
     */
    public static function calculateFor(
        float $amount,
        ?string $countryCode = null,
        bool $countryTaxesApplicable = false,
        bool $taxExempt = false,
    ): array {
        if ($taxExempt) {
            return ['taxes' => [], 'total_tax' => 0.0];
        }

        $activeTaxes = self::active()->applicableToCountry($countryCode, $countryTaxesApplicable)->get();
        $results = [];
        $runningTotal = $amount;
        $totalTax = 0.0;

        foreach ($activeTaxes as $tax) {
            $taxAmount = round(
                $tax->is_compound
                    ? $runningTotal * ($tax->rate / 100)
                    : $amount * ($tax->rate / 100),
                2,
                PHP_ROUND_HALF_UP,
            );

            $results[] = [
                'id' => $tax->id,
                'name' => $tax->name,
                'rate' => $tax->rate,
                'amount' => $taxAmount,
            ];

            $runningTotal += $taxAmount;
            $totalTax += $taxAmount;
        }

        return [
            'taxes' => $results,
            'total_tax' => round($totalTax, 2, PHP_ROUND_HALF_UP),
        ];
    }

    /**
     * Country opt-in is deployment-controlled; exemption metadata is managed by
     * platform administrators, never accepted from tenant billing requests.
     *
     * @return array{taxes: list<array<string, mixed>>, total_tax: float}
     */
    public static function calculateForTenant(float $amount, Tenant $tenant): array
    {
        return self::calculateFor(
            $amount,
            $tenant->country,
            countryTaxesApplicable: (bool) config('billing.country_taxes_enabled', false),
            taxExempt: ($tenant->meta['tax_exempt'] ?? false) === true,
        );
    }

    /**
     * @param  Builder<Tax>  $query
     * @return Builder<Tax>
     */
    public function scopeApplicableToCountry(Builder $query, ?string $countryCode = null, bool $countryTaxesApplicable = false): Builder
    {
        $countryCode = $countryCode === null ? null : mb_strtoupper(mb_trim($countryCode));

        $countryCode = in_array($countryCode, ['GHA', 'GHANA'], true) ? 'GH' : $countryCode;

        return $query->where(function (Builder $countries) use ($countryCode, $countryTaxesApplicable): void {
            $countries->whereNull('country_code');

            if ($countryTaxesApplicable && $countryCode !== null && $countryCode !== '') {
                $countries->orWhere('country_code', $countryCode);
            }
        });
    }

    /**
     * @param  Builder<Tax>  $query
     * @return Builder<Tax>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('priority');
    }
}
