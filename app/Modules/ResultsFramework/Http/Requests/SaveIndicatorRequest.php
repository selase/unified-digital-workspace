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
            'programme' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:32'],
            'source' => ['required', 'string'],
            'baseline' => ['nullable', 'numeric'],
            'target' => ['required', 'numeric'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['name.required' => 'Name the indicator.', 'target.required' => 'Set a target.', 'starts_on.required' => 'Say when measuring starts.', 'ends_on.required' => 'Say when measuring ends.'];
    }
}
