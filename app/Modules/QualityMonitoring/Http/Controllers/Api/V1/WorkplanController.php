<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\QualityMonitoring\Http\Requests\WorkplanApproveRequest;
use App\Modules\QualityMonitoring\Http\Requests\WorkplanRejectRequest;
use App\Modules\QualityMonitoring\Http\Requests\WorkplanStoreRequest;
use App\Modules\QualityMonitoring\Http\Requests\WorkplanSubmitRequest;
use App\Modules\QualityMonitoring\Http\Requests\WorkplanUpdateRequest;
use App\Modules\QualityMonitoring\Models\Review;
use App\Modules\QualityMonitoring\Models\Workplan;
use App\Modules\QualityMonitoring\Models\WorkplanVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function submit(WorkplanSubmitRequest $request, Workplan $workplan): JsonResponse
    {
        $workplan->load('versions');

        $versionNo = ($workplan->versions()->max('version_no') ?? 0) + 1;

        $payload = $workplan->only([
            'title',
            'period_start',
            'period_end',
            'status',
            'owner_id',
            'org_scope',
            'metadata',
        ]);

        DB::transaction(function () use ($workplan, $versionNo, $payload, $request): void {
            WorkplanVersion::create([
                'workplan_id' => $workplan->id,
                'version_no' => $versionNo,
                'status' => 'submitted',
                'payload' => $payload,
                'submitted_at' => now(),
                'created_by' => $request->user()?->id,
            ]);

            $workplan->status = 'submitted';
            $workplan->save();
        });

        return response()->json($workplan->refresh());
    }

    public function approve(WorkplanApproveRequest $request, Workplan $workplan): JsonResponse
    {
        $latest = $workplan->versions()->latest('version_no')->first();

        Review::create([
            'workplan_id' => $workplan->id,
            'reviewer_id' => $request->user()?->id,
            'status' => 'approved',
            'comments' => $request->input('comments'),
            'submitted_at' => now(),
            'approved_at' => now(),
        ]);

        if ($latest) {
            $latest->status = 'approved';
            $latest->approved_at = now();
            $latest->save();
        }

        $workplan->status = 'approved';
        $workplan->save();

        return response()->json($workplan->refresh());
    }

    public function reject(WorkplanRejectRequest $request, Workplan $workplan): JsonResponse
    {
        $latest = $workplan->versions()->latest('version_no')->first();

        Review::create([
            'workplan_id' => $workplan->id,
            'reviewer_id' => $request->user()?->id,
            'status' => 'rejected',
            'comments' => $request->input('comments'),
            'submitted_at' => now(),
        ]);

        if ($latest) {
            $latest->status = 'rejected';
            $latest->save();
        }

        $workplan->status = 'rejected';
        $workplan->save();

        return response()->json($workplan->refresh());
    }
}
