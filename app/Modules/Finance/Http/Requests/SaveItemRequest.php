<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests;

use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveItemRequest extends FormRequest
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
            'sku' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:1000'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'track_stock' => ['nullable', 'boolean'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'opening_quantity' => ['nullable', 'numeric', 'min:0'],
            'income_account_id' => ['nullable', Rule::exists('fin_accounts', 'id')->where('tenant_id', $tenantId)->where('type', 'income')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [

            'name.required' => 'Enter the item name.',
            'unit_price.required' => 'Enter a price.',
        ];
    }
}
