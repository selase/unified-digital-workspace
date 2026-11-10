<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SaveTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('memberships.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'fee' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'period_months' => ['required', 'integer', 'min:1', 'max:120'],
            'is_public' => ['nullable', 'boolean'],
            'auto_invoice' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['name.required' => 'Give the membership type a name.', 'fee.required' => 'Enter the fee (0 for free).', 'period_months.required' => 'Say how many months one payment lasts.'];
    }
}
