<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests;

use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('finance.bank.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (string) app(TenantContext::class)->activeTenantId();

        return [
            'name' => ['required', 'string', 'max:255'],
            'account_id' => ['required', Rule::exists('fin_accounts', 'id')->where('tenant_id', $tenantId)->where('type', 'asset'), Rule::unique('fin_bank_accounts', 'account_id')->where('tenant_id', $tenantId)],
            'institution' => ['nullable', 'string', 'max:255'],
            'account_reference' => ['nullable', 'string', 'max:64'],
            'opening_balance' => ['nullable', 'numeric'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Give the bank account a name.',
            'account_id.required' => 'Choose the ledger account that tracks this bank account.',
            'account_id.unique' => 'That ledger account is already linked to a bank account.',
        ];
    }
}
