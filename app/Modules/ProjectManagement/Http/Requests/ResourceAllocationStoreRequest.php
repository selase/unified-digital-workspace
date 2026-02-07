<?php

declare(strict_types=1);

namespace App\Modules\ProjectManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ResourceAllocationStoreRequest extends FormRequest
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
            'user_id' => ['required', 'uuid'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'allocation_percent' => ['required', 'integer', 'min:1', 'max:100'],
            'role' => ['nullable', 'string', 'max:255'],
        ];
    }
}
