<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Resources;

use App\Modules\HrmsCore\Models\Recruitment\Interview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Interview
 */
final class InterviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'application' => $this->whenLoaded('application', fn () => new CandidateApplicationResource($this->application)),
            'stage' => $this->whenLoaded('stage', fn () => new InterviewStageResource($this->stage)),
            'type' => $this->type,
            'interview_date' => $this->interview_date->format('Y-m-d'),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'location' => $this->location,
            'meeting_link' => $this->meeting_link,
            'status' => $this->status->value,
            'instructions' => $this->instructions,
            'feedback_summary' => $this->feedback_summary,
            'overall_rating' => $this->overall_rating,
            'is_recommended' => $this->is_recommended,
            'scheduled_by' => $this->scheduled_by,
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'panel_members' => InterviewPanelResource::collection($this->whenLoaded('panelMembers')),
            'evaluations' => InterviewEvaluationResource::collection($this->whenLoaded('evaluations')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
