<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests\Appraisal;

use Illuminate\Foundation\Http\FormRequest;

final class StoreAppraisalGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return ($user?->can('hrms.appraisal.create') ?? false)
            || ($user?->can('hrms.appraisal.evaluate') ?? false)
            || ($user?->can('hrms.appraisal.manage') ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'key_results' => ['nullable', 'string', 'max:2000'],
            'target' => ['nullable', 'string', 'max:1000'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'due_date' => ['nullable', 'date'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Please enter a title for the appraisal goal.',
            'weight.max' => 'Goal weight cannot exceed 100%.',
        ];
    }
}
