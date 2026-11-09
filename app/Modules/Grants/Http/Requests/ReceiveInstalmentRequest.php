<?php

declare(strict_types=1);

namespace App\Modules\Grants\Http\Requests;

use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReceiveInstalmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('grants.record');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (string) app(TenantContext::class)->activeTenantId();

        return [
            'date' => ['required', 'date'],
            'deposit_account_id' => ['nullable', Rule::exists('fin_accounts', 'id')->where('tenant_id', $tenantId)->where('type', 'asset')],
            'reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [

        ];
    }
}
