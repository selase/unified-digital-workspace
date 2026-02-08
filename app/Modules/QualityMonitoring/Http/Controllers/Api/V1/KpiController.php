<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\QualityMonitoring\Http\Requests\KpiStoreRequest;
use App\Modules\QualityMonitoring\Http\Requests\KpiUpdateRequest;
use App\Modules\QualityMonitoring\Models\Activity;
use App\Modules\QualityMonitoring\Models\Kpi;
use Illuminate\Http\JsonResponse;

final class KpiController extends Controller
{
    public function store(KpiStoreRequest $request, Activity $activity): JsonResponse
    {
        $kpi = Kpi::create([
            'activity_id' => $activity->id,
            ...$request->validated(),
        ]);

        return response()->json($kpi, 201);
    }

    public function update(KpiUpdateRequest $request, Activity $activity, Kpi $kpi): JsonResponse
    {
        if ($kpi->activity_id !== $activity->id) {
            abort(404);
        }

        $kpi->fill($request->validated());
        $kpi->save();

        return response()->json($kpi);
    }

    public function destroy(Activity $activity, Kpi $kpi): JsonResponse
    {
        abort_if(! request()->user()?->can('qm.kpis.manage'), 403);

        if ($kpi->activity_id !== $activity->id) {
            abort(404);
        }

        $kpi->delete();

        return response()->json([], 204);
    }
}
