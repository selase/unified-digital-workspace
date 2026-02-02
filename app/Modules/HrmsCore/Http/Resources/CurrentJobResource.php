<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Resources;

use App\Modules\HrmsCore\Models\Employees\CurrentJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CurrentJob
 */
final class CurrentJobResource extends JsonResource
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
            'job_title' => $this->job_title,
            'description' => $this->description,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'is_current' => $this->is_current,
            'supervisor_id' => $this->supervisor_id,
            'supervisor' => $this->whenLoaded('supervisor', fn () => new EmployeeResource($this->supervisor)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
