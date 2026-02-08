<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\QualityMonitoring\Http\Requests\ActivityStoreRequest;
use App\Modules\QualityMonitoring\Http\Requests\ActivityUpdateRequest;
use App\Modules\QualityMonitoring\Models\Activity;
use App\Modules\QualityMonitoring\Models\Objective;
use Illuminate\Http\JsonResponse;

final class ActivityController extends Controller
{
    public function store(ActivityStoreRequest $request, Objective $objective): JsonResponse
    {
        $activity = Activity::create([
            'objective_id' => $objective->id,
            ...$request->validated(),
        ]);

        return response()->json($activity, 201);
    }

    public function update(ActivityUpdateRequest $request, Objective $objective, Activity $activity): JsonResponse
    {
        if ($activity->objective_id !== $objective->id) {
            abort(404);
        }

        $activity->fill($request->validated());
        $activity->save();

        return response()->json($activity);
    }

    public function destroy(Objective $objective, Activity $activity): JsonResponse
    {
        abort_if(! request()->user()?->can('qm.workplans.manage'), 403);

        if ($activity->objective_id !== $objective->id) {
            abort(404);
        }

        $activity->delete();

        return response()->json([], 204);
    }
}
