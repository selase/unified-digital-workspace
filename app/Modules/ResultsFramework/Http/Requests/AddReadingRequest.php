<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AddReadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('results.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'value' => ['required', 'numeric'],
            'recorded_on' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
            'breakdowns' => ['sometimes', 'array:gender,age_band,location'],
            'breakdowns.*' => ['array', 'list', 'max:50'],
            'breakdowns.*.*' => ['array:bucket,value'],
            'breakdowns.*.*.bucket' => ['nullable', 'string', 'max:255'],
            'breakdowns.*.*.value' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'value.required' => 'Enter the figure.',
            'recorded_on.required' => 'Enter the date of the reading.',
            'breakdowns.array' => 'Choose gender, age band or location breakdowns.',
            'breakdowns.*.array' => 'Enter a list of breakdown buckets.',
            'breakdowns.*.list' => 'Enter a list of breakdown buckets.',
            'breakdowns.*.max' => 'Enter no more than 50 buckets per breakdown.',
            'breakdowns.*.*.array' => 'Enter a label and figure for each bucket.',
            'breakdowns.*.*.bucket.string' => 'Enter a valid bucket label.',
            'breakdowns.*.*.bucket.max' => 'Use 255 characters or fewer for the bucket label.',
            'breakdowns.*.*.value.numeric' => 'Enter a numeric bucket figure.',
            'breakdowns.*.*.value.min' => 'Enter a non-negative bucket figure.',
            'breakdowns.*.*.value.max' => 'Enter a smaller bucket figure.',
        ];
    }
}
