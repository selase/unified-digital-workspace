<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\QualityMonitoring\Http\Requests\VarianceStoreRequest;
use App\Modules\QualityMonitoring\Models\Activity;
use App\Modules\QualityMonitoring\Models\Variance;
use Illuminate\Http\JsonResponse;

final class VarianceController extends Controller
{
    public function store(VarianceStoreRequest $request, Activity $activity): JsonResponse
    {
        $variance = Variance::create([
            'activity_id' => $activity->id,
            'workplan_id' => $activity->objective?->workplan_id,
            ...$request->validated(),
        ]);

        return response()->json($variance, 201);
    }
}
