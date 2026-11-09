<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests;

use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('finance.bills.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (string) app(TenantContext::class)->activeTenantId();

        return [
            'contact_id' => ['required', Rule::exists('fin_contacts', 'id')->where('tenant_id', $tenantId)->where('is_vendor', true)],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'vendor_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.amount' => ['required', 'numeric', 'gt:0'],
            'lines.*.account_id' => ['required', Rule::exists('fin_accounts', 'id')->where('tenant_id', $tenantId)->whereIn('type', ['expense', 'asset'])],
            'lines.*.fund_id' => ['nullable', Rule::exists('fin_funds', 'id')->where('tenant_id', $tenantId)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contact_id.required' => 'Choose the supplier.',
            'contact_id.exists' => 'Choose a contact marked as a supplier.',
            'lines.required' => 'Add at least one line.',
            'lines.*.amount.gt' => 'Each line amount must be more than zero.',
            'lines.*.account_id.required' => 'Choose an expense account for each line.',
        ];
    }
}
