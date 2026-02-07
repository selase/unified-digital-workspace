<?php

declare(strict_types=1);

namespace App\Modules\ProjectManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class MilestoneStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'sort_order' => ['nullable', 'integer'],
        ];
    }
}
