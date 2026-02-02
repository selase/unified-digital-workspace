<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Resources;

use App\Modules\HrmsCore\Models\Recruitment\JobPosting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JobPosting
 */
final class JobPostingResource extends JsonResource
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
            'requisition' => $this->whenLoaded('requisition', fn () => new JobRequisitionResource($this->requisition)),
            'title' => $this->title,
            'description' => $this->description,
            'requirements' => $this->requirements,
            'benefits' => $this->benefits,
            'slug' => $this->slug,
            'is_internal' => $this->is_internal,
            'is_external' => $this->is_external,
            'is_active' => $this->is_active,
            'posted_date' => $this->posted_date?->format('Y-m-d'),
            'closing_date' => $this->closing_date?->format('Y-m-d'),
            'views_count' => $this->views_count,
            'applications_count' => $this->applications_count,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
