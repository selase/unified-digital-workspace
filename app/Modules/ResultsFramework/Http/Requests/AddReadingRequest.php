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
        return ['value' => ['required', 'numeric'], 'recorded_on' => ['required', 'date'], 'note' => ['nullable', 'string', 'max:255']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['value.required' => 'Enter the figure.', 'recorded_on.required' => 'Enter the date of the reading.'];
    }
}
