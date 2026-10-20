<?php

declare(strict_types=1);

namespace App\Modules\Store\Http\Requests;

use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PlatformCreditPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(TenantContext::class)->getTenant() !== null
            && ($this->user()?->can('store.platform.purchase') ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'invoice_id' => ['prohibited'],
            'package_id' => [
                'required', 'integer',
                Rule::exists('landlord.platform_credit_packages', 'id')
                    ->where('is_active', true)
                    ->where('currency', app(TenantContext::class)->getTenant()?->billingCurrency()),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'invoice_id.prohibited' => 'Invoices cannot be used to purchase platform credit packages.',
            'package_id.required' => 'Select a platform credit package.',
            'package_id.integer' => 'The selected package is invalid.',
            'package_id.exists' => 'Select an active package in your organisation billing currency.',
        ];
    }
}
