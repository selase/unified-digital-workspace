<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;

// Or View if Blade

final class BillingController extends Controller
{
    public function index(Request $request, \App\Services\Tenancy\TenantContext $tenantContext)
    {
        $tenant = $tenantContext->getTenant(); // Resolve via DI

        // If we are in a tenant context
        if (! $tenant) {
            // Fallback or error?
            // Depending on architecture, user might be accessing billing for their "personal" account or "selected" tenant.
            // Assuming tenant context is set by middleware.
            $tenant = auth()->user()->latestTenant; // Fallback?
        }

        $transactions = Transaction::where('tenant_id', $tenant->id)
            ->latest()
            ->paginate(10);

        $invoices = \App\Models\Invoice::where('tenant_id', $tenant->id)
            ->where('status', '!=', \App\Models\Invoice::STATUS_DRAFT)
            ->latest()
            ->get();

        $subscription = $tenant->latestSubscription;
        $subscription = $tenant->latestSubscription;

        // Analytics: Last 6 months revenue
        $sixMonthsAgo = now()->subMonths(5)->startOfMonth();
        $monthlyData = Transaction::where('tenant_id', $tenant->id)
            ->whereIn('status', ['success', 'succeeded'])
            ->where('created_at', '>=', $sixMonthsAgo)
            ->get()
            ->groupBy(function ($val) {
                return \Carbon\Carbon::parse($val->created_at)->format('M'); // Group by Month Name (Jan, Feb)
            });

        // Fill standard 6 month buckets
        $monthlyStats = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthLabel = $date->format('M');

            $transactionsInMonth = $monthlyData->get($monthLabel, collect([]));
            $total = $transactionsInMonth->sum('amount');

            $monthlyStats[] = [
                'label' => $monthLabel,
                'amount' => $total, // In cents
                'formatted' => number_format($total / 100, 2),
            ];
        }

        $accruedMetered = $this->calculateAccruedMetered($tenant);

        return view('billing.index', [
            'tenant' => $tenant,
            'transactions' => $transactions,
            'invoices' => $invoices,
            'subscription' => $subscription,
            'monthlyStats' => $monthlyStats,
            'accruedMetered' => $accruedMetered,
        ]);
    }

    public function pricing(Request $request, \App\Services\Tenancy\TenantContext $tenantContext)
    {
        $tenant = $tenantContext->getTenant();
        $packages = \App\Models\Package::where('is_active', true)->with('features')->get();

        return view('billing.pricing', [
            'tenant' => $tenant,
            'packages' => $packages,
            'currentPackage' => $tenant->package,
        ]);
    }

    private function calculateAccruedMetered(\App\Models\Tenant $tenant): float
    {
        $pricingService = app(\App\Services\Tenancy\PricingService::class);
        $total = 0.0;
        $start = now()->startOfMonth();

        foreach (\App\Enum\UsageMetric::cases() as $metric) {
            $usageValue = \App\Models\UsageRollup::where('tenant_id', $tenant->id)
                ->where('period', 'day')
                ->where('period_start', '>=', $start)
                ->where('metric', $metric)
                ->sum('value');

            if ($usageValue <= 0) {
                continue;
            }

            $total += $pricingService->calculateCost($tenant, $metric, (float) $usageValue);
        }

        return $total;
    }
}
