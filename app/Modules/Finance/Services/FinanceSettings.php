<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\FinSetting;

final class FinanceSettings
{
    public const DEFAULT_PREFIXES = [
        'invoice' => 'INV-',
        'quote' => 'QUO-',
        'payment' => 'PAY-',
        'journal' => 'JNL-',
    ];

    public function for(string $tenantId): FinSetting
    {
        $settings = FinSetting::query()->firstOrCreate(
            ['tenant_id' => $tenantId],
            ['base_currency' => 'GHS', 'prefixes' => self::DEFAULT_PREFIXES],
        );

        // A freshly created row only carries the attributes we set; load the column defaults too.
        return $settings->wasRecentlyCreated ? $settings->refresh() : $settings;
    }

    public function prefix(FinSetting $settings, string $key): string
    {
        $prefixes = (array) $settings->prefixes;

        return (string) ($prefixes[$key] ?? self::DEFAULT_PREFIXES[$key] ?? '');
    }
}
