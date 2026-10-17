<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests\Appraisal;

use App\Modules\HrmsCore\Enums\AppraisalCycle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AppraisalPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hrms.appraisal.manage') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'cycle' => ['required', Rule::enum(AppraisalCycle::class)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'seed_default_scales' => ['nullable', 'boolean'],
            'create_default_template' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Please enter a name for the appraisal period.',
            'cycle.required' => 'Please select the appraisal cycle type.',
            'start_date.required' => 'Please specify the period start date.',
            'end_date.required' => 'Please specify the period end date.',
            'end_date.after_or_equal' => 'The period end date must be on or after the start date.',
        ];
    }
}
