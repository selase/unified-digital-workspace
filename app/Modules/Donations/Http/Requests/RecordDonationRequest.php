<?php

declare(strict_types=1);

namespace App\Modules\Donations\Http\Requests;

use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RecordDonationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('donations.record');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (string) app(TenantContext::class)->activeTenantId();

        return [
            'donor_id' => ['required', Rule::exists('don_donors', 'id')->where('tenant_id', $tenantId)],
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::in(['bank_transfer', 'cash', 'cheque', 'mobile_money', 'card', 'other'])],
            'campaign_id' => ['nullable', Rule::exists('don_campaigns', 'id')->where('tenant_id', $tenantId)],
            'fund_id' => ['nullable', Rule::exists('fin_funds', 'id')->where('tenant_id', $tenantId)],
            'deposit_account_id' => ['nullable', Rule::exists('fin_accounts', 'id')->where('tenant_id', $tenantId)->where('type', 'asset')],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'donor_id.required' => 'Choose the donor.',
            'amount.gt' => 'The amount must be more than zero.',
        ];
    }
}
