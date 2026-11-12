<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SaveIndicatorRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'programme_id' => ['nullable', 'integer'],
            'result_id' => ['nullable', 'integer', 'min:1'],
            'definition' => ['nullable', 'string'],
            'means_of_verification' => ['nullable', 'string'],
            'confirm_source_change' => ['sometimes', 'boolean'],
            'unit' => ['nullable', 'string', 'max:32'],
            'measure_key' => ['present', 'nullable', 'string', 'max:255'],
            'measure_filters' => ['sometimes', 'array'],
            'measure_filters.*' => ['array'],
            'measure_filters.*.*' => ['nullable', 'string', 'max:255'],
            'baseline' => ['nullable', 'numeric'],
            'target' => ['required', 'numeric'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ];
    }

    /** @return array<string, mixed> */
    public function indicatorFields(?string $previousKey = null): array
    {
        $fields = $this->validated();
        $key = $fields['measure_key'];
        $selected = $key === null ? null : ($fields['measure_filters'][$key] ?? null);
        unset($fields['measure_filters']);

        if ($selected !== null || $key !== $previousKey) {
            $fields['measure_filters'] = array_filter($selected ?? [], fn (?string $value): bool => $value !== null && $value !== '');
        }

        return $fields;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['name.required' => 'Name the indicator.', 'target.required' => 'Set a target.', 'starts_on.required' => 'Say when measuring starts.', 'ends_on.required' => 'Say when measuring ends.', 'ends_on.after_or_equal' => 'The indicator cannot end before it starts.', 'programme_id.integer' => 'Choose a programme.', 'result_id.integer' => 'Choose a result.', 'confirm_source_change.boolean' => 'Confirm the source change.',
            'measure_key.present' => 'Choose a source for the figure.',
            'measure_key.string' => 'Choose a valid measure.',
            'measure_key.max' => 'Choose a valid measure.',
            'measure_filters.array' => 'Choose valid measure filters.',
            'measure_filters.*.array' => 'Choose valid measure filters.',
            'measure_filters.*.*.string' => 'Choose a valid filter option.',
            'measure_filters.*.*.max' => 'Choose a valid filter option.',
        ];
    }
}
