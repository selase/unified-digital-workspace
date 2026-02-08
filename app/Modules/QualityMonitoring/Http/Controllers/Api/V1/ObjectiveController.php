<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\QualityMonitoring\Http\Requests\ObjectiveStoreRequest;
use App\Modules\QualityMonitoring\Http\Requests\ObjectiveUpdateRequest;
use App\Modules\QualityMonitoring\Models\Objective;
use App\Modules\QualityMonitoring\Models\Workplan;
use Illuminate\Http\JsonResponse;

final class ObjectiveController extends Controller
{
    public function store(ObjectiveStoreRequest $request, Workplan $workplan): JsonResponse
    {
        $objective = Objective::create([
            'workplan_id' => $workplan->id,
            ...$request->validated(),
        ]);

        return response()->json($objective, 201);
    }

    public function update(ObjectiveUpdateRequest $request, Workplan $workplan, Objective $objective): JsonResponse
    {
        if ($objective->workplan_id !== $workplan->id) {
            abort(404);
        }

        $objective->fill($request->validated());
        $objective->save();

        return response()->json($objective);
    }

    public function destroy(Workplan $workplan, Objective $objective): JsonResponse
    {
        abort_if(! request()->user()?->can('qm.workplans.manage'), 403);

        if ($objective->workplan_id !== $workplan->id) {
            abort(404);
        }

        $objective->delete();

        return response()->json([], 204);
    }
}
