<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests;

use App\Modules\Finance\Support\Money;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('finance.payments.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (string) app(TenantContext::class)->activeTenantId();

        return [

            'contact_id' => ['required', Rule::exists('fin_contacts', 'id')->where('tenant_id', $tenantId)],
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::in(['bank_transfer', 'cash', 'cheque', 'mobile_money', 'card', 'other'])],
            'reference' => ['nullable', 'string', 'max:255'],
            'deposit_account_id' => ['nullable', Rule::exists('fin_accounts', 'id')->where('tenant_id', $tenantId)->where('type', 'asset')],
            'notes' => ['nullable', 'string', 'max:1000'],
            'allocations' => ['nullable', 'array'],
            'allocations.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [

            'contact_id.required' => 'Choose the customer who paid.',
            'amount.gt' => 'The amount must be more than zero.',
        ];
    }

    /**
     * @return array<int, int> invoice id => minor units
     */
    public function allocationAmounts(): array
    {
        return collect((array) $this->validated('allocations', []))
            ->map(fn ($value): int => Money::toMinor($value))
            ->filter(fn (int $amount): bool => $amount > 0)
            ->mapWithKeys(fn (int $amount, $invoiceId): array => [(int) $invoiceId => $amount])
            ->all();
    }
}
