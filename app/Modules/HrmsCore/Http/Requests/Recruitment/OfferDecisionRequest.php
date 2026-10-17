<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class OfferDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('hrms.applications.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::in(['accept', 'reject', 'withdraw'])],
            'candidate_feedback' => ['required_if:decision,reject', 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decision.required' => 'Please select an offer decision.',
            'decision.in' => 'Decision must be accept, reject, or withdraw.',
            'candidate_feedback.required_if' => 'Candidate feedback is required when recording an offer rejection.',
        ];
    }
}
