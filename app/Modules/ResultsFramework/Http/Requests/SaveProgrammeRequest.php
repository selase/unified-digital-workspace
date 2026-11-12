<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Http\Requests;

use App\Modules\ResultsFramework\Models\ResultProgramme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveProgrammeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('results.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(ResultProgramme::STATUSES)],
            'grant_id' => ['nullable', 'integer', 'min:1'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', ...($this->filled('starts_on') ? ['after_or_equal:starts_on'] : [])],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Name the programme.',
            'status.in' => 'Choose a valid programme status.',
            'grant_id.integer' => 'Choose a valid grant.',
            'grant_id.min' => 'Choose a valid grant.',
            'starts_on.date' => 'Enter a valid start date.',
            'ends_on.date' => 'Enter a valid end date.',
            'ends_on.after_or_equal' => 'The programme must end on or after it starts.',
        ];
    }
}
