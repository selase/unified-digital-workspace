<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class GenerateTargetsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('results.manage');
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['frequency' => ['required', 'string', 'in:monthly,quarterly']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'frequency.required' => 'Choose a target frequency.',
            'frequency.string' => 'Choose monthly or quarterly targets.',
            'frequency.in' => 'Choose monthly or quarterly targets.',
        ];
    }
}
