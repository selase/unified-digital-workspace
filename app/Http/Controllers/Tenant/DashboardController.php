<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;

final class DashboardController extends Controller
{
    public function index(): Factory|View
    {
        $tenant = app(\App\Services\Tenancy\TenantContext::class)->getTenant();
        $meteringService = app(\App\Services\Tenancy\FeatureMeteringService::class);

        // Fetch usage for some common metered features
        $usages = [];
        foreach ($tenant->features()->where('enabled', true)->get() as $feature) {
            if (isset($feature->meta['type']) && $feature->meta['type'] === 'limit') {
                $usages[] = $meteringService->getUsage($tenant, $feature->feature_key);
            }
        }

        // Checklist Logic
        $checklist = [
            'onboarding' => (bool) $tenant->onboarding_completed_at,
            'team' => $tenant->users()->count() > 1,
            'branding' => (! empty($tenant->logo) || ! empty(data_get($tenant->meta, 'branding.primary_color')) || ! empty(data_get($tenant->meta, 'primary_color'))),
        ];

        return view('tenant.dashboard', compact('usages', 'checklist'));
    }
}
