<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests\Appraisal;

use Illuminate\Foundation\Http\FormRequest;

final class StoreAppraisalCompetencyRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'behavioral_indicators' => ['nullable', 'string', 'max:2000'],
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
            'name.required' => 'Please enter the competency name.',
            'weight.max' => 'Competency weight cannot exceed 100%.',
        ];
    }
}
