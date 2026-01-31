<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Enum\UsageMetric;
use App\Models\Tenant;
use App\Models\UsageLimit;
use App\Models\UsageRollup;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

final class UsageLimitService
{
    /**
     * Check if a tenant is within their limits for a specific metric.
     */
    public function isWithinLimits(Tenant $tenant, UsageMetric $metric, float $additionalUsage = 0): bool
    {
        $limit = UsageLimit::active()
            ->where('tenant_id', $tenant->id)
            ->where('metric', $metric)
            ->first();

        if (! $limit) {
            return true; // No limit defined
        }

        $currentUsage = $this->getCurrentUsage($tenant, $metric, $limit->period);

        if (($currentUsage + $additionalUsage) > (float) $limit->limit_value) {
            if ($limit->block_on_limit) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get aggregated usage for a tenant for a metric and period.
     */
    private function getCurrentUsage(Tenant $tenant, UsageMetric $metric, string $periodType): float
    {
        $start = match($periodType) {
            'daily' => now()->startOfDay(),
            'monthly' => now()->startOfMonth(),
            default => now()->startOfMonth(),
        };

        // We use 'day' rollups to sum up to the month/day
        return (float) UsageRollup::where('tenant_id', $tenant->id)
            ->where('metric', $metric)
            ->where('period', 'day')
            ->where('period_start', '>=', $start)
            ->sum('value');
    }

    /**
     * Evaluate all active limits and trigger alerts if needed.
     */
    public function evaluateAlerts(Tenant $tenant): void
    {
        $limits = UsageLimit::active()->where('tenant_id', $tenant->id)->get();

        foreach ($limits as $limit) {
            $usage = $this->getCurrentUsage($tenant, $limit->metric, $limit->period);
            $percent = ($usage / (float) $limit->limit_value) * 100;

            if ($percent >= $limit->alert_threshold) {
                // Throttle alerts to once per day
                if (! $limit->last_alert_at || $limit->last_alert_at->isBefore(now()->startOfDay())) {
                    $this->triggerAlert($tenant, $limit, $percent);
                    $limit->update(['last_alert_at' => now()]);
                }
            }
        }
    }

    private function triggerAlert(Tenant $tenant, UsageLimit $limit, float $percent): void
    {
        Log::warning("Usage Alert for Tenant {$tenant->name}: {$limit->metric->value} is at " . round($percent, 2) . "% capacity.");
        
        // TODO: Send Email / Slack Notification
    }
}
