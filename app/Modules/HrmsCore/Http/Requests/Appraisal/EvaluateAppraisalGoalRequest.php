<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests\Appraisal;

use App\Modules\HrmsCore\Enums\GoalStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class EvaluateAppraisalGoalRequest extends FormRequest
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
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'key_results' => ['nullable', 'string', 'max:2000'],
            'target' => ['nullable', 'string', 'max:1000'],
            'achievement' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::enum(GoalStatus::class)],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'self_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'supervisor_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'employee_comments' => ['nullable', 'string', 'max:2000'],
            'supervisor_comments' => ['nullable', 'string', 'max:2000'],
            'due_date' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'self_rating.min' => 'Goal self-rating must be between 1 and 5.',
            'self_rating.max' => 'Goal self-rating must be between 1 and 5.',
            'supervisor_rating.min' => 'Goal supervisor rating must be between 1 and 5.',
            'supervisor_rating.max' => 'Goal supervisor rating must be between 1 and 5.',
        ];
    }
}
