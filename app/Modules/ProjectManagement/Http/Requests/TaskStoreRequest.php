<?php

declare(strict_types=1);

namespace App\Modules\ProjectManagement\Http\Requests;

use App\Modules\ProjectManagement\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TaskStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('projects.tasks.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'milestone_id' => ['nullable', 'integer', 'exists:milestones,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in(Task::STATUSES)],
            'priority' => ['nullable', 'string', Rule::in(Task::PRIORITIES)],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'completed_at' => ['nullable', 'date'],
            'estimated_minutes' => ['nullable', 'integer', 'min:0'],
            'sort_order' => ['nullable', 'integer'],
            'parent_id' => ['nullable', 'integer', 'exists:tasks,id'],
        ];
    }
}
