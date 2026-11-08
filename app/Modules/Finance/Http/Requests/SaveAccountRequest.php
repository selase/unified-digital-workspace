<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests;

use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('finance.accounts.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (string) app(TenantContext::class)->activeTenantId();

        return [

            'code' => ['required', 'string', 'max:32', Rule::unique('fin_accounts', 'code')->where('tenant_id', $tenantId)->ignore($this->route('account'), 'uuid')],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['asset', 'liability', 'equity', 'income', 'expense'])],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [

            'code.unique' => 'That account code is already used.',
            'type.in' => 'Choose a valid account type.',
        ];
    }
}
