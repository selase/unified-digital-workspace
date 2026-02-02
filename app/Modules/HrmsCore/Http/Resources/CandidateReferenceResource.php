<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Resources;

use App\Modules\HrmsCore\Models\Recruitment\CandidateReference;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CandidateReference
 */
final class CandidateReferenceResource extends JsonResource
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
            'name' => $this->name,
            'relationship' => $this->relationship,
            'company' => $this->company,
            'position' => $this->position,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'contacted_at' => $this->contacted_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'feedback' => $this->feedback,
            'rating' => $this->rating,
            'is_recommended' => $this->is_recommended,
            'notes' => $this->notes,
            'checked_by' => $this->whenLoaded('checkedBy', fn () => new EmployeeResource($this->checkedBy)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
