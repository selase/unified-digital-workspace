<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests\Promotion;

use Illuminate\Foundation\Http\FormRequest;

final class ReviewStaffPromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', 'in:approve,reject'],
            'comments' => ['nullable', 'string', 'max:2000'],
            'rejection_reason' => ['required_if:decision,reject', 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decision.required' => 'Please select a review decision (Approve or Reject).',
            'decision.in' => 'The selected review decision is not valid.',
            'rejection_reason.required_if' => 'Please provide a rejection reason when rejecting a promotion request.',
        ];
    }
}
