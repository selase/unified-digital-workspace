<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as InputValidator;

final class HybridPricingService
{
    /** @return array<string, list<mixed>> */
    public static function rules(): array
    {
        return [
            'selfServiceUsers' => ['required', 'integer', 'min:0', 'max:100000'],
            'operationalUsers' => ['required', 'integer', 'min:0', 'max:100000'],
            'selectedPackages' => ['present', 'array', 'max:16'],
            'selectedPackages.*' => ['required', 'string', Rule::in(array_keys(config('usage-pricing.hybrid.packages')))],
            'quoteCurrency' => ['required', Rule::in(['GHS', 'USD'])],
            'websitePlan' => ['required', Rule::in(array_keys(config('usage-pricing.hybrid.website_plans')))],
            'websiteEditors' => ['required', 'integer', 'min:0', 'max:100000'],
            'includeSetup' => ['required', 'boolean'],
            'fileStorageGiB' => ['required', 'numeric', 'min:0', 'max:100000'],
            'databaseGiB' => ['required', 'numeric', 'min:0', 'max:100000'],
            'bandwidthGiB' => ['required', 'numeric', 'min:0', 'max:100000'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'selfServiceUsers.*' => 'Self-service users must be a whole number between 0 and 100,000.',
            'operationalUsers.*' => 'Operational users must be a whole number between 0 and 100,000.',
            'quoteCurrency.*' => 'Choose GHS or USD.',
            'selectedPackages.*' => 'Choose a published module package.',
            'selectedPackages.*.*' => 'Choose a published module package.',
            'websitePlan.*' => 'Choose a website plan.',
            'websiteEditors.*' => 'Website editors must be a whole number between 0 and 100,000.',
            'includeSetup.*' => 'Choose whether this estimate includes initial setup.',
            'fileStorageGiB.*' => 'Enter file storage in GiB as a non-negative number.',
            'databaseGiB.*' => 'Enter shared database size in GiB as a non-negative number.',
            'bandwidthGiB.*' => 'Enter monthly website bandwidth in GiB as a non-negative number.',
        ];
    }

    /** @param array<string, mixed> $data */
    public static function validator(array $data): InputValidator
    {
        return Validator::make($data, self::rules(), self::messages())->after(function (InputValidator $validator) use ($data): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $plan = config('usage-pricing.hybrid.website_plans.'.$data['websitePlan']);
            foreach ([
                'fileStorageGiB' => ['files_gib', 'File storage'],
                'databaseGiB' => ['database_gib', 'Shared database size'],
                'bandwidthGiB' => ['bandwidth_gib', 'Monthly bandwidth'],
            ] as $field => [$limit, $label]) {
                if ((float) $data[$field] > $plan[$limit]) {
                    $validator->errors()->add($field, "{$label} exceeds the {$plan['name']} allowance of {$plan[$limit]} GiB. Choose a larger plan or request a capacity quote.");
                }
            }
        });
    }

    /**
     * Estimate only: never persists a subscription, entitlement or invoice rate.
     *
     * @param  array<int, string>  $packages
     * @param  array{websitePlan?: string, websiteEditors?: int, includeSetup?: bool, fileStorageGiB?: float, databaseGiB?: float, bandwidthGiB?: float}  $website
     * @return array{currency: string, website_plan: string, base_fee: float, active_users: int, access_before_discount: float, access_discount: float, access_total: float, packages_total: float, total: float, setup_fee: float, first_month_total: float, included_editors: int, extra_editors: int, extra_editor_total: float, access_lines: list<array{name: string, quantity: int, unit_price: float, before_discount: float, discount: float, subtotal: float}>, package_lines: list<array{name: string, subtotal: float}>, modules: list<string>, features: list<string>}
     */
    public function quote(int $selfServiceUsers, int $operationalUsers, array $packages, string $currency, array $website = []): array
    {
        $catalogue = config('usage-pricing.hybrid.packages');
        $data = [
            'selfServiceUsers' => $selfServiceUsers,
            'operationalUsers' => $operationalUsers,
            'selectedPackages' => $packages,
            'quoteCurrency' => $currency,
            'websitePlan' => $website['websitePlan'] ?? 'essentials',
            'websiteEditors' => $website['websiteEditors'] ?? 0,
            'includeSetup' => $website['includeSetup'] ?? false,
            'fileStorageGiB' => $website['fileStorageGiB'] ?? 0,
            'databaseGiB' => $website['databaseGiB'] ?? 0,
            'bandwidthGiB' => $website['bandwidthGiB'] ?? 0,
        ];
        self::validator($data)->validate();

        $accessLines = [];
        $grossCents = 0;
        $accessCents = 0;
        foreach (['self_service' => $selfServiceUsers, 'operational' => $operationalUsers] as $type => $quantity) {
            $rate = config('usage-pricing.hybrid.access.'.$type);
            $unitCents = (int) round($rate[$currency] * 100);
            $net = $this->accessCostInCents($quantity, $unitCents);
            $gross = $quantity * $unitCents;
            $grossCents += $gross;
            $accessCents += $net;
            $accessLines[] = [
                'name' => $rate['name'], 'quantity' => $quantity,
                'unit_price' => $unitCents / 100.0,
                'before_discount' => $gross / 100.0,
                'discount' => ($gross - $net) / 100.0,
                'subtotal' => $net / 100.0,
            ];
        }

        $packageLines = [];
        $packageCents = 0;
        $modules = config('usage-pricing.hybrid.included_modules');
        $features = ['custom-domains'];
        foreach (array_unique($packages) as $slug) {
            $package = $catalogue[$slug];
            $cents = (int) round($package[$currency] * 100);
            $packageCents += $cents;
            $modules = [...$modules, ...$package['modules']];
            $features = [...$features, ...$package['features']];
            $packageLines[] = ['name' => $package['name'], 'subtotal' => $cents / 100.0];
        }

        $plan = config('usage-pricing.hybrid.website_plans.'.$data['websitePlan']);
        $coreCents = (int) round($plan[$currency] * 100);
        $extraEditors = max(0, $data['websiteEditors'] - $plan['editors']);
        $editorCents = $extraEditors * (int) round(config('usage-pricing.hybrid.extra_editor.'.$currency) * 100);
        $setupCents = $data['includeSetup'] ? (int) round($plan['setup'][$currency] * 100) : 0;
        $totalCents = $coreCents + $accessCents + $packageCents + $editorCents;

        return [
            'currency' => $currency, 'base_fee' => $coreCents / 100.0,
            'website_plan' => $plan['name'],
            'included_editors' => $plan['editors'],
            'extra_editors' => $extraEditors, 'extra_editor_total' => $editorCents / 100.0,
            'setup_fee' => $setupCents / 100.0, 'first_month_total' => ($totalCents + $setupCents) / 100.0,
            'active_users' => $selfServiceUsers + $operationalUsers,
            'access_before_discount' => $grossCents / 100.0,
            'access_discount' => ($grossCents - $accessCents) / 100.0,
            'access_total' => $accessCents / 100.0,
            'packages_total' => $packageCents / 100.0,
            'total' => $totalCents / 100.0,
            'access_lines' => $accessLines, 'package_lines' => $packageLines,
            'modules' => array_values(array_unique($modules)),
            'features' => array_values(array_unique($features)),
        ];
    }

    private function accessCostInCents(int $quantity, int $unitCents): int
    {
        $previous = 0;
        $hundredthsOfCent = 0;
        foreach (config('usage-pricing.hybrid.bands') as $band) {
            $upper = $band['up_to'] ?? $quantity;
            $count = max(0, min($quantity, $upper) - $previous);
            $hundredthsOfCent += $count * $unitCents * (100 - $band['discount_percent']);
            $previous = $upper;
            if ($quantity <= $upper) {
                break;
            }
        }

        return (int) round($hundredthsOfCent / 100, 0, PHP_ROUND_HALF_UP);
    }
}
