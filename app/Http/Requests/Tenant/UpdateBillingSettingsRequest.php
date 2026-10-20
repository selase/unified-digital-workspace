<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateBillingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('core.settings.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'billing_email' => ['required', 'email'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'billing_address' => ['nullable', 'string', 'max:255'],
            'billing_currency' => $this->user()?->can('access-superadmin-dashboard')
                ? ['nullable', 'in:GHS,USD']
                : ['missing'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'billing_currency.in' => 'Choose GHS, USD, or automatic currency from your country.',
            'billing_currency.missing' => 'Only a supplier administrator can override billing currency.',
        ];
    }
}
