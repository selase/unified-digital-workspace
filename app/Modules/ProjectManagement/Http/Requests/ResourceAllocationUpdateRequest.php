<?php

declare(strict_types=1);

namespace App\Modules\ProjectManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ResourceAllocationUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('projects.allocations.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['sometimes', 'required', 'date', 'after_or_equal:start_date'],
            'allocation_percent' => ['sometimes', 'required', 'integer', 'min:1', 'max:100'],
            'role' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
