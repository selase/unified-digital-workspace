<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests;

use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('finance.expenses.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (string) app(TenantContext::class)->activeTenantId();

        return [
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'account_id' => ['required', Rule::exists('fin_accounts', 'id')->where('tenant_id', $tenantId)->where('type', 'expense')],
            'paid_from_account_id' => ['required', Rule::exists('fin_accounts', 'id')->where('tenant_id', $tenantId)->where('type', 'asset')],
            'contact_id' => ['nullable', Rule::exists('fin_contacts', 'id')->where('tenant_id', $tenantId)],
            'tax_id' => ['nullable', Rule::exists('fin_taxes', 'id')->where('tenant_id', $tenantId)],
            'fund_id' => ['nullable', Rule::exists('fin_funds', 'id')->where('tenant_id', $tenantId)],
            'description' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'account_id.required' => 'Choose the expense account.',
            'account_id.exists' => 'Choose an expense account from the chart of accounts.',
            'paid_from_account_id.required' => 'Choose the account the money was paid from.',
            'amount.gt' => 'The amount must be more than zero.',
        ];
    }
}
