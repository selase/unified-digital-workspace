<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests\Salary;

use Illuminate\Foundation\Http\FormRequest;

final class AllowanceTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return ($user?->can('hrms.allowances.manage') ?? false)
            || ($user?->can('hrms.salary.manage') ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_taxable' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Please enter an allowance category name.',
        ];
    }
}
