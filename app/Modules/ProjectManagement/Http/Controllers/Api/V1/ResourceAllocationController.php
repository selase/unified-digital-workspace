<?php

declare(strict_types=1);

namespace App\Modules\ProjectManagement\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\ProjectManagement\Http\Requests\ResourceAllocationStoreRequest;
use App\Modules\ProjectManagement\Http\Requests\ResourceAllocationUpdateRequest;
use App\Modules\ProjectManagement\Http\Resources\ResourceAllocationResource;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\ResourceAllocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

final class ResourceAllocationController extends Controller
{
    public function store(ResourceAllocationStoreRequest $request, Project $project): JsonResponse
    {
        $data = $request->validated();
        $data['project_id'] = $project->id;

        $this->assertNoOverlap($project->id, $data['user_id'], $data['start_date'], $data['end_date']);

        $allocation = ResourceAllocation::create($data);

        return (new ResourceAllocationResource($allocation))->response()->setStatusCode(201);
    }

    public function update(ResourceAllocationUpdateRequest $request, Project $project, ResourceAllocation $allocation): ResourceAllocationResource
    {
        $this->ensureProjectRelationship($project, $allocation);

        $data = $request->validated();

        $start = Arr::get($data, 'start_date', $allocation->start_date?->toDateString());
        $end = Arr::get($data, 'end_date', $allocation->end_date?->toDateString());

        $this->assertNoOverlap($project->id, $allocation->user_id, $start, $end, $allocation->id);

        $allocation->fill($data);
        $allocation->save();

        return new ResourceAllocationResource($allocation);
    }

    public function destroy(Project $project, ResourceAllocation $allocation): JsonResponse
    {
        $this->authorize('projects.allocations.manage');
        $this->ensureProjectRelationship($project, $allocation);

        $allocation->delete();

        return response()->json([], 204);
    }

    private function assertNoOverlap(int $projectId, string $userId, string $start, string $end, ?int $ignoreId = null): void
    {
        $overlapQuery = ResourceAllocation::query()
            ->where('project_id', $projectId)
            ->where('user_id', $userId)
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start);

        if ($ignoreId) {
            $overlapQuery->where('id', '!=', $ignoreId);
        }

        if ($overlapQuery->exists()) {
            abort(422, 'Allocation overlaps with existing assignment.');
        }
    }

    private function ensureProjectRelationship(Project $project, ResourceAllocation $allocation): void
    {
        if ($allocation->project_id !== $project->id) {
            abort(404);
        }
    }
}
