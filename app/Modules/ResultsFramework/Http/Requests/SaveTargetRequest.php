<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SaveTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('results.manage');
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'period_start' => ['required', 'date_format:Y-m-d'],
            'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start'],
            'target_value' => ['required', 'numeric', 'between:-999999999999.99,999999999999.99', 'decimal:0,2'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'period_start.required' => 'Choose the first day of the period.',
            'period_start.date_format' => 'Enter a valid period start date.',
            'period_end.required' => 'Choose the last day of the period.',
            'period_end.date_format' => 'Enter a valid period end date.',
            'period_end.after_or_equal' => 'The period cannot end before it starts.',
            'target_value.required' => 'Enter the period target.',
            'target_value.numeric' => 'Enter a numeric period target.',
            'target_value.between' => 'Enter a smaller period target.',
            'target_value.decimal' => 'Use up to two decimal places for the period target.',
        ];
    }
}
