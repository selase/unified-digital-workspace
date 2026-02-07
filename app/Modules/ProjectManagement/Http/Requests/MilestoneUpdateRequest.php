<?php

declare(strict_types=1);

namespace App\Modules\ProjectManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class MilestoneUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('projects.milestones.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'completed_at' => ['sometimes', 'nullable', 'date'],
            'sort_order' => ['sometimes', 'nullable', 'integer'],
        ];
    }
}
