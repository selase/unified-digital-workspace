<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Http\Requests;

use App\Modules\ResultsFramework\Models\ResultNode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveResultNodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('results.manage');
    }

    public function rules(): array
    {
        $placement = ['parent_id' => ['nullable', 'integer', 'min:1'], 'sort_order' => ['nullable', 'integer', 'min:0', 'max:2147483647']];
        if ($this->routeIs('results-framework.programmes.results.move')) {
            return $placement;
        }

        return $placement + [
            'level' => ['required', Rule::in(array_keys(ResultNode::LEVELS))],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assumptions' => ['nullable', 'string'],
            'risks' => ['nullable', 'string'],
            'project_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'level.required' => 'Choose a result level.',
            'level.in' => 'Choose goal, outcome, output or activity.',
            'title.required' => 'Enter a result title.',
            'parent_id.integer' => 'Choose a parent result.',
            'sort_order.integer' => 'Enter a whole number for the position.',
            'sort_order.min' => 'The position cannot be negative.',
            'project_id.integer' => 'Choose a project.',
        ];
    }
}
