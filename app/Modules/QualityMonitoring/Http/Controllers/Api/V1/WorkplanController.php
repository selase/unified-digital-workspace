<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\QualityMonitoring\Http\Requests\WorkplanStoreRequest;
use App\Modules\QualityMonitoring\Http\Requests\WorkplanUpdateRequest;
use App\Modules\QualityMonitoring\Models\Workplan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WorkplanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        abort_if(! $request->user()?->can('qm.workplans.view'), 403);

        $query = Workplan::query();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('period_start')) {
            $query->whereDate('period_start', '>=', $request->date('period_start'));
        }

        if ($request->filled('period_end')) {
            $query->whereDate('period_end', '<=', $request->date('period_end'));
        }

        $workplans = $query->paginate($request->integer('per_page', 15));

        return response()->json($workplans);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(WorkplanStoreRequest $request): JsonResponse
    {
        $workplan = Workplan::create($request->validated());

        return response()->json($workplan, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Workplan $workplan): JsonResponse
    {
        abort_if(! request()->user()?->can('qm.workplans.view'), 403);

        return response()->json($workplan);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(WorkplanUpdateRequest $request, Workplan $workplan): JsonResponse
    {
        $workplan->fill($request->validated());
        $workplan->save();

        return response()->json($workplan);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Workplan $workplan): JsonResponse
    {
        abort_if(! request()->user()?->can('qm.workplans.manage'), 403);

        $workplan->delete();

        return response()->json([], 204);
    }
}
