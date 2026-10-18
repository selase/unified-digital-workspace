<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Resources;

use App\Modules\HrmsCore\Enums\AppraisalStatus;
use App\Modules\HrmsCore\Models\Appraisal\Appraisal;
use App\Modules\HrmsCore\Models\Employees\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Appraisal
 */
final class AppraisalResource extends JsonResource
{
    public static function viewerCanSeeSupervisorEvaluation(Request $request, ?Appraisal $appraisal): bool
    {
        if ($appraisal === null) {
            return false;
        }

        if ($appraisal->status === AppraisalStatus::Complete) {
            return true;
        }

        $user = $request->user();
        if ($user?->can('hrms.appraisal.manage') ?? false) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        $actorEmployee = Employee::query()
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id);
                if ($user->email !== '') {
                    $query->orWhere('email', $user->email);
                }
            })
            ->first();

        if ($actorEmployee === null || $actorEmployee->id === $appraisal->employee_id) {
            return false;
        }

        return $appraisal->reviews()->where('reviewer_id', $actorEmployee->id)->exists();
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canSeeSupervisorEvaluation = $this->canSeeSupervisorEvaluation($request);

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'employee' => $this->whenLoaded('employee', fn () => new EmployeeResource($this->employee)),
            'period' => $this->whenLoaded('period', fn () => new AppraisalPeriodResource($this->period)),
            'template' => $this->whenLoaded('template', fn () => new AppraisalTemplateResource($this->template)),
            'status' => $this->status->value,
            'self_assessment_submitted_at' => $this->self_assessment_submitted_at?->toIso8601String(),
            'supervisor_reviewed_at' => $this->supervisor_reviewed_at?->toIso8601String(),
            'hod_reviewed_at' => $this->hod_reviewed_at?->toIso8601String(),
            'hr_reviewed_at' => $this->hr_reviewed_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'self_overall_score' => $this->self_overall_score,
            'supervisor_overall_score' => $canSeeSupervisorEvaluation ? $this->supervisor_overall_score : null,
            'final_overall_score' => $canSeeSupervisorEvaluation ? $this->final_overall_score : null,
            'responses' => AppraisalResponseResource::collection($this->whenLoaded('responses')),
            'goals' => AppraisalGoalResource::collection($this->whenLoaded('goals')),
            'reviews' => $canSeeSupervisorEvaluation
                ? AppraisalReviewResource::collection($this->whenLoaded('reviews'))
                : [],
            'scores' => AppraisalScoreResource::collection($this->whenLoaded('scores')),
            'recommendations' => AppraisalRecommendationResource::collection($this->whenLoaded('recommendations')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function canSeeSupervisorEvaluation(Request $request): bool
    {
        return self::viewerCanSeeSupervisorEvaluation($request, $this->resource);
    }
}
