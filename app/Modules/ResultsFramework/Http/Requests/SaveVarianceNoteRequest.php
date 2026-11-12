<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SaveVarianceNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('results.manage');
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string'], 'corrective_action' => ['nullable', 'string']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.required' => 'Enter the reason for the variance.',
            'reason.string' => 'Enter the reason as text.',
            'corrective_action.string' => 'Enter the corrective action as text.',
        ];
    }
}
