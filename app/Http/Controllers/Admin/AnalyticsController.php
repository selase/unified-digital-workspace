<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enum\UsageMetric;
use App\Http\Controllers\Controller;
use App\Models\UsageRollup;
use App\Services\Tenancy\PricingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AnalyticsController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('access-superadmin-dashboard');

        $tenantId = $request->input('tenant_id');
        $startDateParam = $request->input('start_date');
        $endDateParam = $request->input('end_date');
        $days = $request->integer('days', 7);

        if ($startDateParam && $endDateParam) {
            $startDate = Carbon::parse($startDateParam)->startOfDay();
            $endDate = Carbon::parse($endDateParam)->endOfDay();
        } else {
            $startDate = Carbon::now()->subDays($days)->startOfDay();
            $endDate = Carbon::now()->endOfDay();
        }

        $tenants = \App\Models\Tenant::select('id', 'name')->orderBy('name')->get();

        $baseQuery = UsageRollup::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('period_start', [$startDate, $endDate]);

        // 1. Request Throughput (Hourly)
        $requestStats = (clone $baseQuery)
            ->where('metric', UsageMetric::REQUEST_COUNT)
            ->where('period', 'hour')
            ->selectRaw('period_start, SUM(value) as total')
            ->groupBy('period_start')
            ->orderBy('period_start')
            ->get();

        // 2. Error Rate (Status Buckets)
        $statusBreakdown = (clone $baseQuery)
            ->where('metric', UsageMetric::REQUEST_COUNT)
            ->where('period', 'hour')
            ->get()
            ->groupBy(fn ($item) => $item->dimensions['status_bucket'] ?? 'unknown')
            ->map(fn ($group) => $group->sum('value'));

        // 3. Peak Hours Heatmap
        $peakHours = (clone $baseQuery)
            ->where('metric', UsageMetric::REQUEST_COUNT)
            ->where('period', 'hour')
            ->selectRaw('EXTRACT(HOUR FROM period_start) as hour, SUM(value) as total')
            ->groupBy('hour')
            ->orderBy('hour')
            ->pluck('total', 'hour')
            ->toArray();

        $heatmapData = [];
        for ($i = 0; $i < 24; $i++) {
            $heatmapData[] = (int) ($peakHours[$i] ?? 0);
        }

        // 4. Resource Trends (Daily)
        $storageStats = (clone $baseQuery)
            ->where('metric', UsageMetric::STORAGE_BYTES)
            ->where('period', 'day')
            ->selectRaw('period_start, SUM(value) as total')
            ->groupBy('period_start')
            ->orderBy('period_start')
            ->get();

        $dbStats = (clone $baseQuery)
            ->where('metric', UsageMetric::DB_BYTES)
            ->where('period', 'day')
            ->selectRaw('period_start, SUM(value) as total')
            ->groupBy('period_start')
            ->orderBy('period_start')
            ->get();

        // 5. Per-Tenant Cost Estimates
        $costEstimates = $this->calculateCostEstimates($startDate, $endDate, $tenantId);

        return view('admin.analytics.usage', [
            'days' => $days,
            'tenants' => $tenants,
            'selectedTenantId' => $tenantId,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'requestTrend' => [
                'labels' => $requestStats->pluck('period_start')->map(fn ($d) => $d->format('m-d H:i'))->toArray(),
                'data' => $requestStats->pluck('total')->toArray(),
            ],
            'statusBreakdown' => [
                'labels' => $statusBreakdown->keys()->toArray(),
                'data' => $statusBreakdown->values()->toArray(),
            ],
            'peakHours' => $heatmapData,
            'storageTrend' => [
                'labels' => $storageStats->pluck('period_start')->map(fn ($d) => $d->format('m-d'))->toArray(),
                'data' => $storageStats->pluck('total')->map(fn ($v) => round($v / 1024 / 1024 / 1024, 2))->toArray(), // GB
            ],
            'dbTrend' => [
                'labels' => $dbStats->pluck('period_start')->map(fn ($d) => $d->format('m-d'))->toArray(),
                'data' => $dbStats->pluck('total')->map(fn ($v) => round($v / 1024 / 1024, 2))->toArray(), // MB
            ],
            'costEstimates' => $costEstimates,
            'breadcrumbs' => [
                ['link' => route('dashboard'), 'name' => __('Home')],
                ['name' => __('Usage Analytics')],
            ],
        ]);
    }

    /**
     * @return array<int, array{tenant_name: string, metrics: array<string, array{usage: float, cost: float, unit: string}>, total: float}>
     */
    private function calculateCostEstimates(Carbon $startDate, Carbon $endDate, ?string $tenantId): array
    {
        $pricingService = app(PricingService::class);

        $query = \App\Models\Tenant::query()->where('status', 'active');
        if ($tenantId) {
            $query->where('id', $tenantId);
        }

        $tenants = $query->orderBy('name')->get();
        $estimates = [];

        foreach ($tenants as $tenant) {
            $tenantCosts = [
                'tenant_name' => $tenant->name,
                'metrics' => [],
                'total' => 0.0,
            ];

            foreach (UsageMetric::cases() as $metric) {
                $usageValue = UsageRollup::where('tenant_id', $tenant->id)
                    ->where('period', 'day')
                    ->whereBetween('period_start', [$startDate, $endDate])
                    ->where('metric', $metric)
                    ->sum('value');

                if ($usageValue <= 0) {
                    continue;
                }

                $cost = $pricingService->calculateCost($tenant, $metric, (float) $usageValue);

                $tenantCosts['metrics'][$metric->value] = [
                    'usage' => (float) $usageValue,
                    'cost' => $cost,
                    'unit' => $metric->unit(),
                ];
                $tenantCosts['total'] += $cost;
            }

            if (! empty($tenantCosts['metrics'])) {
                $estimates[] = $tenantCosts;
            }
        }

        usort($estimates, fn ($a, $b) => $b['total'] <=> $a['total']);

        return $estimates;
    }
}
