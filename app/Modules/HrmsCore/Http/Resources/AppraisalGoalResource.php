<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Resources;

use App\Modules\HrmsCore\Models\Appraisal\AppraisalGoal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AppraisalGoal
 */
final class AppraisalGoalResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'key_results' => $this->key_results,
            'target' => $this->target,
            'achievement' => $this->achievement,
            'status' => $this->status->value,
            'weight' => $this->weight,
            'self_rating' => $this->self_rating,
            'supervisor_rating' => $this->supervisor_rating,
            'employee_comments' => $this->employee_comments,
            'supervisor_comments' => $this->supervisor_comments,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
