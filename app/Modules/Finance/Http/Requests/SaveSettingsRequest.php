<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests;

use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

final class SaveSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('finance.settings.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (string) app(TenantContext::class)->activeTenantId();

        return [

            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:1000'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'base_currency' => ['required', 'string', 'size:3', 'alpha'],
            'fiscal_year_start_month' => ['required', 'integer', 'between:1,12'],
            'default_payment_terms_days' => ['required', 'integer', 'min:0', 'max:365'],
            'lock_date' => ['nullable', 'date'],
            'tax_enabled' => ['sometimes', 'boolean'],
            'evat_enabled' => ['sometimes', 'boolean'],
            'invoice_footer' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [

            'base_currency.size' => 'Use a three-letter currency code such as GHS.',
        ];
    }
}
