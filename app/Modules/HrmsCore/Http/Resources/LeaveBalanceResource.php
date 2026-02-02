<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Resources;

use App\Modules\HrmsCore\Models\Leave\LeaveBalance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LeaveBalance
 */
final class LeaveBalanceResource extends JsonResource
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
            'employee' => $this->whenLoaded('employee', fn () => new EmployeeResource($this->employee)),
            'category' => $this->whenLoaded('leaveCategory', fn () => new LeaveCategoryResource($this->leaveCategory)),
            'year' => $this->year,
            'entitled_days' => $this->entitled_days,
            'used_days' => $this->used_days,
            'carried_forward_days' => $this->carried_forward_days,
            'remaining_days' => $this->remaining_days,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
