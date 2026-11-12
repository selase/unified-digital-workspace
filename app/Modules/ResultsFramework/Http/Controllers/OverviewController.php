<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ResultsFramework\Models\ResultIndicator;
use App\Modules\ResultsFramework\Models\ResultProgramme;
use App\Modules\ResultsFramework\Services\IndicatorService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class OverviewController extends Controller
{
    public function __invoke(Request $request, TenantContext $context, IndicatorService $indicators): View
    {
        $viewer = $request->user();
        abort_unless($viewer?->can('results.view'), 403);
        $tenantId = $context->activeTenantId();
        abort_unless($tenantId, 403);

        $active = ResultIndicator::query()->where('tenant_id', $tenantId)->where('status', 'active')->with('tenant')->get()
            ->map(fn (ResultIndicator $indicator): array => [
                'programme_id' => $indicator->programme_id,
                'progress' => $indicators->progress($indicator, $viewer, includePeriods: false),
            ]);
        $groups = $active->groupBy('programme_id');
        $programmes = ResultProgramme::query()->where('tenant_id', $tenantId)->orderBy('name')->get()
            ->map(function (ResultProgramme $programme) use ($groups): array {
                $results = $groups->get($programme->id, collect())->pluck('progress');
                $restricted = $results->contains('status', 'restricted');
                $average = $restricted ? null : $results->pluck('percent')->filter(fn ($percent): bool => $percent !== null)
                    ->map(fn (int $percent): int => min(100, $percent))->avg();

                return [
                    'programme' => $programme,
                    'count' => $results->count(),
                    'restricted' => $restricted,
                    'percent' => $average === null ? null : (int) round($average),
                ];
            });

        return view('results-framework::overview', [
            'counts' => $active->countBy('progress.status'),
            'programmes' => $programmes,
        ]);
    }
}
