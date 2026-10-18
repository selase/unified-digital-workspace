<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Resources;

use App\Modules\HrmsCore\Models\Appraisal\AppraisalScore;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AppraisalScore
 */
final class AppraisalScoreResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canSeeSupervisor = AppraisalResource::viewerCanSeeSupervisorEvaluation($request, $this->appraisal);

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'section' => $this->whenLoaded('section', fn () => new AppraisalSectionResource($this->section)),
            'self_score' => $this->self_score,
            'supervisor_score' => $canSeeSupervisor ? $this->supervisor_score : null,
            'final_score' => $canSeeSupervisor ? $this->final_score : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
