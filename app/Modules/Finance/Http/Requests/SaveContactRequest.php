<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests;

use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

final class SaveContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('finance.contacts.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (string) app(TenantContext::class)->activeTenantId();

        return [

            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:1000'],
            'tax_number' => ['nullable', 'string', 'max:64'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'is_customer' => ['sometimes', 'boolean'],
            'is_vendor' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [

            'name.required' => 'Enter the contact name.',
            'email.email' => 'Enter a valid email address.',
        ];
    }
}
