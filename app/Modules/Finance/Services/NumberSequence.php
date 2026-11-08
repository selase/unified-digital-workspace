<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\FinSequence;
use Illuminate\Support\Facades\DB;

/**
 * Gap-free, per-tenant document numbers. Must be called inside the caller's transaction
 * so a rolled-back document gives its number back.
 */
final class NumberSequence
{
    public function next(string $tenantId, string $key, string $prefix, int $pad = 5): string
    {
        return DB::connection('tenant')->transaction(function () use ($tenantId, $key, $prefix, $pad): string {
            FinSequence::query()->firstOrCreate(['tenant_id' => $tenantId, 'key' => $key], ['last_number' => 0]);

            $sequence = FinSequence::query()
                ->where('tenant_id', $tenantId)
                ->where('key', $key)
                ->lockForUpdate()
                ->firstOrFail();

            $sequence->last_number++;
            $sequence->save();

            return $prefix.mb_str_pad((string) $sequence->last_number, $pad, '0', STR_PAD_LEFT);
        });
    }
}
