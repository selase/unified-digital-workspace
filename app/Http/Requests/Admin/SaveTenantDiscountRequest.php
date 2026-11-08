<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\TenantDiscount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveTenantDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-superadmin-dashboard') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $percent = $this->input('type') === TenantDiscount::TYPE_PERCENT;

        return [
            'tenant_id' => ['required', 'uuid', Rule::exists('landlord.tenants', 'id')],
            'name' => ['required', 'string', 'max:120'],
            'reason' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in([TenantDiscount::TYPE_PERCENT, TenantDiscount::TYPE_FIXED])],
            'value' => ['required', 'numeric', 'gt:0', $percent ? 'max:100' : 'max:10000000'],
            'currency' => [Rule::requiredIf(! $percent), 'nullable', Rule::in(['GHS', 'USD'])],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'value.gt' => 'The discount must be greater than zero.',
            'value.max' => 'A percentage discount cannot exceed 100.',
            'currency.required' => 'Choose the currency for a fixed-amount discount.',
            'ends_on.after_or_equal' => 'The end date cannot be before the start date.',
        ];
    }
}
