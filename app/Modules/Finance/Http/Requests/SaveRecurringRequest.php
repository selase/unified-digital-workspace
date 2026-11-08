<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests;

use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveRecurringRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('finance.invoices.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (string) app(TenantContext::class)->activeTenantId();

        return [

            'name' => ['required', 'string', 'max:255'],
            'contact_id' => ['required', Rule::exists('fin_contacts', 'id')->where('tenant_id', $tenantId)],
            'frequency' => ['required', Rule::in(['weekly', 'monthly', 'quarterly', 'yearly'])],
            'next_run_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:next_run_date'],
            'terms_days' => ['required', 'integer', 'min:0', 'max:365'],
            'auto_send' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:500'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.tax_id' => ['nullable', Rule::exists('fin_taxes', 'id')->where('tenant_id', $tenantId)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [

            'contact_id.required' => 'Choose a customer.',
            'lines.required' => 'Add at least one line.',
        ];
    }
}
