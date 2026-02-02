<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Resources;

use App\Modules\HrmsCore\Models\Recruitment\OfferNegotiation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OfferNegotiation
 */
final class OfferNegotiationResource extends JsonResource
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
            'round' => $this->round,
            'initiated_by' => $this->initiated_by,
            'request' => $this->request,
            'response' => $this->response,
            'status' => $this->status,
            'requested_salary' => $this->requested_salary,
            'offered_salary' => $this->offered_salary,
            'requested_benefits' => $this->requested_benefits,
            'offered_benefits' => $this->offered_benefits,
            'handled_by' => $this->whenLoaded('handledBy', fn () => new EmployeeResource($this->handledBy)),
            'responded_at' => $this->responded_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
