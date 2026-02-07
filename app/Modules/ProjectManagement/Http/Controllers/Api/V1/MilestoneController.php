<?php

declare(strict_types=1);

namespace App\Modules\ProjectManagement\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\ProjectManagement\Http\Requests\MilestoneStoreRequest;
use App\Modules\ProjectManagement\Http\Requests\MilestoneUpdateRequest;
use App\Modules\ProjectManagement\Http\Resources\MilestoneResource;
use App\Modules\ProjectManagement\Models\Milestone;
use App\Modules\ProjectManagement\Models\Project;
use Illuminate\Http\JsonResponse;

final class MilestoneController extends Controller
{
    public function store(MilestoneStoreRequest $request, Project $project): JsonResponse
    {
        $data = $request->validated();
        $data['project_id'] = $project->id;

        $milestone = Milestone::create($data);

        return (new MilestoneResource($milestone))->response()->setStatusCode(201);
    }

    public function update(MilestoneUpdateRequest $request, Project $project, Milestone $milestone): MilestoneResource
    {
        $this->ensureProjectRelationship($project, $milestone);

        $milestone->fill($request->validated());
        $milestone->save();

        return new MilestoneResource($milestone);
    }

    public function destroy(Project $project, Milestone $milestone): JsonResponse
    {
        $this->authorize('projects.milestones.manage');
        $this->ensureProjectRelationship($project, $milestone);

        $milestone->delete();

        return response()->json([], 204);
    }

    private function ensureProjectRelationship(Project $project, Milestone $milestone): void
    {
        if ($milestone->project_id !== $project->id) {
            abort(404);
        }
    }
}
