<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests\Appraisal;

use Illuminate\Foundation\Http\FormRequest;

final class EvaluateAppraisalCompetencyRequest extends FormRequest
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
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'behavioral_indicators' => ['nullable', 'string', 'max:2000'],
            'self_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'self_evidence' => ['nullable', 'string', 'max:2000'],
            'supervisor_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'supervisor_evidence' => ['nullable', 'string', 'max:2000'],
            'final_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'self_rating.min' => 'Self-assessment rating must be between 1 and 5.',
            'self_rating.max' => 'Self-assessment rating must be between 1 and 5.',
            'supervisor_rating.min' => 'Supervisor rating must be between 1 and 5.',
            'supervisor_rating.max' => 'Supervisor rating must be between 1 and 5.',
            'final_rating.min' => 'Final rating must be between 1 and 5.',
            'final_rating.max' => 'Final rating must be between 1 and 5.',
        ];
    }
}
